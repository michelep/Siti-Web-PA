<?php

/**
 * Progetto: Siti Web PA
 * Monitoraggio e analisi dei siti web della Pubblica Amministrazione Italiana
 *
 * @package   SitiWebPA
 * @author    Michele Pinassi <o-zone@zerozone.it>
 * @copyright Copyright (c) 2026 Michele Pinassi
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty|
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.inc.php';

use daandesmedt\PHPHeadlessChrome\HeadlessChrome;
use Aws\S3\S3Client;

// Resolve logger reference
/** @var Monolog\Logger $logger */
$logger = $GLOBALS['logger'] ?? null;

// Lock file setup
$lockFile = sys_get_temp_dir() . '/hourly_pa_checker.lock';

try {
    if (!FileLocker::lockFile($lockFile)) {
        // Stale lock cleanup (older than 2 hours)
        if (file_exists($lockFile) && (time() - filemtime($lockFile) > 7200)) {
            @unlink($lockFile);
        }
        echo "Lock file exists.\n";
        exit(0);
    }

    $r2Config = [
        'accountId'  => defined('R2_ACCOUNT_ID') ? R2_ACCOUNT_ID : 'fe9b0a26bc12956d26ef39ec8552f7b9',
        'accessKey'  => defined('R2_ACCESS_KEY') ? R2_ACCESS_KEY : '8873aad0b464713dbbfb06ec4fa5bb2d',
        'secretKey'  => defined('R2_SECRET_KEY') ? R2_SECRET_KEY : '7a851cca78c7292d6ebd30c6d8c994ce226f7fa461bf9b08c1e418b048ba92d9',
        'bucketName' => defined('R2_BUCKET_NAME') ? R2_BUCKET_NAME : 'faropa',
        'prefix'     => 'screenshots/', // Prefisso/cartella virtuale nel bucket
    ];

    $s3 = new S3Client([
        'version'     => 'latest',
        'region'      => 'auto',
        'endpoint'    => "https://{$r2Config['accountId']}.r2.cloudflarestorage.com",
        'credentials' => [
            'key'    => $r2Config['accessKey'],
            'secret' => $r2Config['secretKey'],
        ],
    ]);

    /**
     * Helper to reliably extract domain host from URL or string
     */
    function extractHost(string $url): ?string
    {
        $rawUrl = trim($url);
        if ($rawUrl === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'http://' . $rawUrl;
        }

        $host = parse_url($rawUrl, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        // Strip leading www.
        return preg_replace('/^www\./i', '', (string)$host);
    }

    /**
     * Case-insensitive helper for str_contains
     */
    function str_contains_insensitive(string $haystack, string $needle): bool
    {
        return str_contains(strtolower($haystack), strtolower($needle));
    }

    /**
     * Resolves hosting infrastructure provider from IP address
     */
    function getHostingInfo(string $ip): ?string
    {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Skip private or loopback IP ranges
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Internal/Private Network';
        }

        $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,org,as,isp");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_USERAGENT      => BOT_USER_AGENT,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (($data['status'] ?? '') !== 'success') {
            return null;
        }

        $org = $data['org'] ?? $data['isp'] ?? $data['as'] ?? '';

        return match (true) {
            str_contains_insensitive($org, 'Amazon') || str_contains_insensitive($org, 'AWS') => 'AWS Cloud',
            str_contains_insensitive($org, 'Microsoft') || str_contains_insensitive($org, 'Azure') => 'Microsoft Azure',
            str_contains_insensitive($org, 'Google') => 'Google Cloud',
            str_contains_insensitive($org, 'Aruba') => 'Aruba S.p.A.',
            str_contains_insensitive($org, 'Telecom Italia') || str_contains_insensitive($org, 'TIM') => 'TIM / Olivetti',
            str_contains_insensitive($org, 'Fastweb') => 'Fastweb',
            str_contains_insensitive($org, 'Wind') || str_contains_insensitive($org, 'Infostrada') => 'WindTre',
            str_contains_insensitive($org, 'Seeweb') => 'Seeweb',
            str_contains_insensitive($org, 'Cineca') => 'CINECA',
            str_contains_insensitive($org, 'Hetzner') => 'Hetzner',
            str_contains_insensitive($org, 'OVH') => 'OVHcloud',
            str_contains_insensitive($org, 'Cloudflare') => 'Cloudflare CDN',
            default => !empty($org) ? $org : 'Unknown Hosting'
        };
    }

    /**
     * Ispeziona la connessione TLS: recupera protocollo negoziato, cifrario e metadati del certificato X.509.
     *
     * @param string $domain Dominio da testare (es. comune.roma.it)
     * @param int $port Porta TLS (default: 443)
     * @param float|int $timeout Timeout in secondi (default: 4)
     * @return array{
     *     connected: bool,
     *     protocol: ?string,
     *     cipher: ?string,
     *     days_remaining: ?int,
     *     valid_to: ?string,
     *     issuer: ?string,
     *     error: ?string
     * }
     */
    function inspectTlsConnection(string $domain, int $port = 443, float|int $timeout = 4): array
    {
        $sslOptions = [
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false, // Non blocca l'ispezione per estrarre dati anche da certificati scaduti
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'SNI_enabled'       => true,
                'peer_name'         => $domain,
            ],
        ];

        $ctx = stream_context_create($sslOptions);
        $errno = 0;
        $errstr = '';

        // Handshake socket con flag asincrono/bloccante calibrato
        $client = @stream_socket_client(
            "ssl://{$domain}:{$port}",
            $errno,
            $errstr,
            (float)$timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$client) {
            return [
                'connected'      => false,
                'protocol'       => null,
                'cipher'         => null,
                'days_remaining' => null,
                'valid_to'       => null,
                'issuer'         => null,
                'error'          => $errstr !== '' ? $errstr : 'Handshake TLS fallito o porta 443 filtrata',
            ];
        }

        // Imposta timeout di lettura sul socket per evitare blocchi sulla negoziazione crypto
        stream_set_timeout($client, (int)ceil($timeout));

        // 1. Estrazione del protocollo reale (TLSv1.3, TLSv1.2, ecc.) e del cifrario
        $meta = stream_get_meta_data($client);
        $cryptoInfo = $meta['crypto'] ?? [];
        $protocol = $cryptoInfo['protocol'] ?? null;
        $cipher   = $cryptoInfo['cipher_name'] ?? null;

        // 2. Estrazione e parsing del certificato X.509
        $params = stream_context_get_params($client);
        fclose($client);

        $peerCert = $params['options']['ssl']['peer_certificate'] ?? null;
        $daysRemaining = null;
        $validTo = null;
        $issuer = null;

        if ($peerCert) {
            $certDetails = openssl_x509_parse($peerCert);
            if ($certDetails && isset($certDetails['validTo_time_t'])) {
                $validToTimestamp = (int)$certDetails['validTo_time_t'];
                $daysRemaining = (int)floor(($validToTimestamp - time()) / 86400);
                $validTo = date('Y-m-d H:i:s', $validToTimestamp);
                $issuer = $certDetails['issuer']['O'] ?? $certDetails['issuer']['CN'] ?? 'Sconosciuto';
            }
        }

        return [
            'connected'      => true,
            'protocol'       => $protocol,
            'cipher'         => $cipher,
            'days_remaining' => $daysRemaining,
            'valid_to'       => $validTo,
            'issuer'         => $issuer,
            'error'          => null,
        ];
    }

    /**
     * Verifica l'accettazione effettiva ed esclusiva dei protocolli deprecati TLS 1.0 e 1.1.
     *
     * @param string $domain Dominio dell'ente
     * @param int $port Porta TLS (default 443)
     * @param float $timeout Timeout per ciascun tentativo
     * @return array{deprecated_supported: array<string>, check_errors: array<string>}
     */
    function probeDeprecatedTls(string $domain, int $port = 443, float $timeout = 2.5): array
    {
        $deprecatedTargets = [
            'TLSv1.0' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT,
            'TLSv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
        ];

        $supported = [];
        $errors = [];

        // Contesto SSL con SECLEVEL=0 per impedire a OpenSSL 3 di bloccare il test sul client
        $sslOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'SNI_enabled'       => true,
                'peer_name'         => $domain,
                'ciphers'           => 'DEFAULT:@SECLEVEL=0',
            ]
        ];

        foreach ($deprecatedTargets as $name => $cryptoMethod) {
            $ctx = stream_context_create($sslOptions);
            $errno = 0;
            $errstr = '';

            // 1. Apri la socket TCP in chiaro senza handshake immediato
            $fp = @stream_socket_client(
                "tcp://{$domain}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $ctx
            );

            if (!$fp) {
                $errors[$name] = "TCP fallito: " . ($errstr ?: 'Timeout');
                continue;
            }

            stream_set_timeout($fp, (int)ceil($timeout));

            // 2. Tenta l'handshake vincolando ESCLUSIVAMENTE la versione testata
            $handshakeSuccess = @stream_socket_enable_crypto($fp, true, $cryptoMethod);
            fclose($fp);

            if ($handshakeSuccess === true) {
                // Il server ha accettato la negoziazione su questa specifica versione obsoleta
                $supported[] = $name;
            }
        }

        return [
            'deprecated_supported' => $supported,
            'check_errors'         => $errors,
        ];
    }

    /**
     * Esegue il check di disponibilità web, stato DNS e audit di sicurezza SSL/TLS.
     *
     * @param string $domain Dominio istituzionale (es. comune.roma.it)
     * @return array{
     *     healthy: bool,
     *     http_code?: int,
     *     protocol?: string,
     *     error: ?string,
     *     tls: ?array
     * }
     */
    function checkWebsiteHealth(string $domain): array
    {
        // 1. Risoluzione DNS A / AAAA (con bitwise OR corretto)
        $dnsRecords = @dns_get_record($domain, DNS_A | DNS_AAAA);
        if (empty($dnsRecords)) {
            return [
                'healthy' => false,
                'error'   => 'DNS Error: Impossibile risolvere il dominio (record A/AAAA assenti)',
                'tls'     => null,
            ];
        }

        // 2. Controllo disponibilità HTTP/HTTPS
        $urlsToTry = ['https://' . $domain, 'http://' . $domain];
        $lastError = null;

        foreach ($urlsToTry as $targetUrl) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $targetUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => defined('BOT_USER_AGENT') ? BOT_USER_AGENT : 'FaroPA-Bot/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            curl_exec($ch);
            $httpCode      = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlErrNo     = curl_errno($ch);
            $curlError     = curl_error($ch);
            $primaryScheme = (string)parse_url($targetUrl, PHP_URL_SCHEME);
            curl_close($ch);

            // Rilevamento errori SSL/TLS nativi
            $isSslError = (
                (defined('CURLE_SSL_CONNECT_ERROR') && $curlErrNo === CURLE_SSL_CONNECT_ERROR) ||
                in_array($curlErrNo, [35, 51, 58, 59, 60, 77, 83], true) ||
                str_contains(strtolower($curlError), 'ssl') ||
                str_contains(strtolower($curlError), 'certificate')
            );

            if ($isSslError) {
                // Estrae comunque i metadati del certificato scaduto/invalido via socket permissivo
                $tlsInfo = inspectTlsConnection($domain, 443, 3);

                return [
                    'healthy' => false,
                    'error'   => 'Errore Certificato SSL/TLS non valido o scaduto: ' . ($curlError !== '' ? $curlError : 'Handshake rifiutato'),
                    'tls'     => [
                        'score'                => 0,
                        'negotiated_version'   => $tlsInfo['protocol'],
                        'cipher'               => $tlsInfo['cipher'],
                        'days_remaining'       => $tlsInfo['days_remaining'],
                        'valid_to'             => $tlsInfo['valid_to'],
                        'issuer'               => $tlsInfo['issuer'],
                        'deprecated_supported' => [],
                        'tags'                 => ['ssl_invalid_or_expired'],
                    ],
                ];
            }

            // Risposta valida HTTP 2xx o 3xx
            if ($httpCode >= 200 && $httpCode < 400) {
                $tlsAudit = null;

                if ($primaryScheme === 'https') {
                    $tlsInfo         = inspectTlsConnection($domain);
                    $deprecatedProbe = probeDeprecatedTls($domain);
                    $deprecatedList  = $deprecatedProbe['deprecated_supported'];

                    $tlsScore = 100;
                    $tlsTags  = [];

                    $negotiated = $tlsInfo['protocol'];
                    if ($negotiated) {
                        $tlsTags[] = 'negotiated_' . strtolower(str_replace(['.', ' '], ['_', ''], $negotiated));
                    }

                    // Penalità per protocolli legacy abilitati
                    if (in_array('TLSv1.0', $deprecatedList, true)) {
                        $tlsScore -= 45;
                        $tlsTags[] = 'deprecated_tls10_enabled';
                    }
                    if (in_array('TLSv1.1', $deprecatedList, true)) {
                        $tlsScore -= 25;
                        $tlsTags[] = 'deprecated_tls11_enabled';
                    }

                    // Penalità e tag sullo stato del certificato
                    if ($tlsInfo['days_remaining'] !== null) {
                        if ($tlsInfo['days_remaining'] < 0) {
                            $tlsScore = 0;
                            $tlsTags[] = 'ssl_expired';
                        } elseif ($tlsInfo['days_remaining'] <= 7) {
                            $tlsScore = max(0, $tlsScore - 20);
                            $tlsTags[] = 'ssl_expiring_soon';
                        } elseif ($tlsInfo['days_remaining'] <= 30) {
                            $tlsTags[] = 'ssl_expiring_soon';
                        }
                    }

                    if (empty($deprecatedList) && $negotiated === 'TLSv1.3' && ($tlsInfo['days_remaining'] ?? 0) > 30) {
                        $tlsTags[] = 'modern_tls_only';
                    }

                    $tlsAudit = [
                        'score'                => max(0, $tlsScore),
                        'negotiated_version'   => $negotiated,
                        'cipher'               => $tlsInfo['cipher'],
                        'days_remaining'       => $tlsInfo['days_remaining'],
                        'valid_to'             => $tlsInfo['valid_to'],
                        'issuer'               => $tlsInfo['issuer'],
                        'deprecated_supported' => $deprecatedList,
                        'tags'                 => array_values(array_unique($tlsTags)),
                    ];
                }

                return [
                    'healthy'   => true,
                    'http_code' => $httpCode,
                    'protocol'  => $primaryScheme,
                    'error'     => null,
                    'tls'       => $tlsAudit,
                ];
            }

            if ($httpCode >= 400) {
                $lastError = sprintf('Errore HTTP %d (%s)', $httpCode, $httpCode === 404 ? 'Pagina non trovata' : 'Errore Server');
            } elseif ($curlErrNo > 0) {
                $lastError = 'Connessione fallita: ' . ($curlError !== '' ? $curlError : 'Timeout o Host irraggiungibile');
            }
        }

        return [
            'healthy' => false,
            'error'   => $lastError ?? 'Sito web non raggiungibile',
            'tls'     => null,
        ];
    }

    /**
     * Normalizza il valore dell'header HTTP 'Server'
     *
     * @param string $rawServer Valore grezzo dell'header Server
     * @return array{name: string|null, version: string|null}
     */
    function parseWebServerHeader(string $rawServer): array
    {
        $raw = trim($rawServer);
        if ($raw === '') {
            return ['name' => null, 'version' => null];
        }

        // 1. Pulizia da duplicati e separatori tipici di catene reverse proxy (es. "Aruba-proxy; aruba-proxy")
        $tokens = preg_split('/[;,]/', $raw);
        $cleanTokens = [];
        $blacklist = ['web', 'unknown', 'server', 'none', '-', 'custom'];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || in_array(strtolower($token), $blacklist, true)) {
                continue;
            }
            $lowerToken = strtolower($token);
            if (!isset($cleanTokens[$lowerToken])) {
                $cleanTokens[$lowerToken] = $token;
            }
        }

        if (empty($cleanTokens)) {
            return ['name' => null, 'version' => null];
        }

        // Lavoriamo sul primo software significativo della catena
        $target = reset($cleanTokens);
        $lowerTarget = strtolower($target);

        // 2. Mappatura ordinata per specificità (OpenResty prima di Nginx, Microsoft-IIS con word-boundary)
        $serverMap = [
            'openresty'     => ['name' => 'OpenResty',     'pattern' => 'openresty'],
            'litespeed'     => ['name' => 'LiteSpeed',     'pattern' => 'litespeed'],
            'caddy'         => ['name' => 'Caddy',         'pattern' => 'caddy'],
            'cloudflare'    => ['name' => 'Cloudflare',    'pattern' => 'cloudflare'],
            'microsoft-iis' => ['name' => 'Microsoft-IIS', 'pattern' => '(?:microsoft-iis|iis)'],
            'nginx'         => ['name' => 'Nginx',         'pattern' => 'nginx'],
            'apache'        => ['name' => 'Apache',        'pattern' => 'apache'],
            'lighttpd'      => ['name' => 'Lighttpd',      'pattern' => 'lighttpd'],
        ];

        $detectedName    = null;
        $detectedVersion = null;

        foreach ($serverMap as $config) {
            if (preg_match('/\b' . $config['pattern'] . '\b/i', $lowerTarget)) {
                $detectedName = $config['name'];

                // Cattura la versione associata (es. Apache/2.4.52 o Microsoft-IIS/10.0)
                $regex = '/\b' . $config['pattern'] . '(?:\/|\s+)?v?([0-9]+(?:\.[0-9]+)+)/i';
                if (preg_match($regex, $target, $matches)) {
                    $detectedVersion = $matches[1];
                }
                break;
            }
        }

        // 3. Fallback per altri software non mappati (es. "Aruba-proxy", "Gunicorn", "Envoy")
        if ($detectedName === null && strlen($target) <= 50) {
            $parts = explode('/', $target, 2);
            $candidateName = trim($parts[0]);

            // Evita di salvare token generici o puramente numerici
            if ($candidateName !== '' && !in_array(strtolower($candidateName), $blacklist, true) && !is_numeric($candidateName)) {
                $detectedName = ucfirst($candidateName);

                if (isset($parts[1]) && preg_match('/^([0-9]+(?:\.[0-9]+)+)/', trim($parts[1]), $vMatch)) {
                    $detectedVersion = $vMatch[1];
                }
            }
        }

        return [
            'name'    => $detectedName,
            'version' => $detectedVersion,
        ];
    }

    /**
     * Recupera gli header HTTP reali seguendo i redirect
     *
     * @param string $url
     * @return array<string, string>
     */
    function fetchHttpHeaders(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_NOBODY         => true, // Richiesta HEAD (veloce, scarica solo header)
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => BOT_USER_AGENT,
        ]);

        $raw = curl_exec($ch);
        curl_close($ch);

        if (!$raw || !is_string($raw)) {
            return [];
        }

        $headers = [];
        $lines = explode("\r\n", $raw);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $normKey = strtolower(trim($k));
                $val     = trim($v);

                if (isset($headers[$normKey])) {
                    $headers[$normKey] .= '; ' . $val;
                } else {
                    $headers[$normKey] = $val;
                }
            }
        }

        return $headers;
    }

    /**
     * Analizza le intestazioni HTTP di sicurezza e restituisce mappa e punteggio
     *
     * @param array<string, string> $headers Header normalizzati in lowercase
     * @return array{hsts: bool, csp: bool, xfo: bool, xxp: bool, rp: bool, score: string}
     */
    function checkSecurityHeaders(array $headers): array
    {
        $lowerHeaders = array_change_key_case($headers, CASE_LOWER);

        $hsts = isset($lowerHeaders['strict-transport-security']);
        $csp  = isset($lowerHeaders['content-security-policy']);
        $xfo  = isset($lowerHeaders['x-frame-options']);
        $xxp  = isset($lowerHeaders['x-content-type-options']) && str_contains(strtolower($lowerHeaders['x-content-type-options']), 'nosniff');
        $rp   = isset($lowerHeaders['referrer-policy']);

        $points = ($hsts ? 1 : 0) + ($csp ? 1 : 0) + ($xfo ? 1 : 0) + ($xxp ? 1 : 0) + ($rp ? 1 : 0);

        $grade = match ($points) {
            5 => 'A+',
            4 => 'A',
            3 => 'B',
            2 => 'C',
            1 => 'D',
            default => 'F',
        };

        return [
            'hsts'  => $hsts,
            'csp'   => $csp,
            'xfo'   => $xfo,
            'xxp'   => $xxp,
            'rp'    => $rp,
            'score' => $grade,
        ];
    }

    /**
     * Analizza gli header HTTP per identificare la presenza di WAF o CDN
     *
     * @param array<string, string> $headers Header normalizzati in lowercase
     * @return array{detected: bool, provider: string|null, details: string|null}
     */
    function detectWafProvider(array $headers): array
    {
        // 1. Cloudflare
        if (!empty($headers['cf-ray']) || isset($headers['cf-mitigated']) || !empty($headers['cf-cache-status'])) {
            $details = !empty($headers['cf-ray']) ? 'RayID: ' . trim($headers['cf-ray']) : null;
            if (isset($headers['cf-mitigated'])) {
                $details .= ' (Mitigated)';
            }
            return ['detected' => true, 'provider' => 'Cloudflare', 'details' => $details];
        }

        // 2. Akamai
        if (!empty($headers['x-akamai-transformed']) || !empty($headers['akamai-grn']) || !empty($headers['x-akamai-request-id'])) {
            return ['detected' => true, 'provider' => 'Akamai', 'details' => 'Akamai Edge/WAF'];
        }

        // 3. AWS WAF / CloudFront
        if (!empty($headers['x-amz-cf-id']) || !empty($headers['x-amzn-waf-action'])) {
            $action = $headers['x-amzn-waf-action'] ?? null;
            return ['detected' => true, 'provider' => 'AWS CloudFront/WAF', 'details' => $action ? "Action: {$action}" : null];
        }

        // 4. Azure Front Door / Application Gateway
        if (!empty($headers['x-azure-ref']) || !empty($headers['x-ms-ref'])) {
            return ['detected' => true, 'provider' => 'Microsoft Azure WAF', 'details' => 'Azure Front Door'];
        }

        // 5. Fastly
        if (!empty($headers['x-fastly-request-id']) || !empty($headers['fastly-restarts'])) {
            return ['detected' => true, 'provider' => 'Fastly', 'details' => 'Fastly Edge Cloud'];
        }

        // 6. Imperva Incapsula
        if (!empty($headers['x-iinfo']) || (isset($headers['x-cdn']) && str_contains(strtolower($headers['x-cdn']), 'imperva'))) {
            return ['detected' => true, 'provider' => 'Imperva', 'details' => 'Incapsula Cloud WAF'];
        }

        // 7. Sucuri
        if (!empty($headers['x-sucuri-id']) || !empty($headers['x-sucuri-cache'])) {
            return ['detected' => true, 'provider' => 'Sucuri', 'details' => 'Sucuri CloudProxy'];
        }

        // 8. F5 BIG-IP (rilevabile spesso tramite i cookie)
        $cookies = $headers['set-cookie'] ?? '';
        if (str_contains($cookies, 'BIGipServer') || preg_match('/TS[0-9a-f]{6,8}=/i', $cookies)) {
            return ['detected' => true, 'provider' => 'F5 BIG-IP', 'details' => 'F5 Traffic / ASM'];
        }

        return ['detected' => false, 'provider' => null, 'details' => null];
    }

    /**
     * Verifica la presenza e la configurazione dei record SPF e DMARC
     *
     * @param string $domain Dominio dell'ente (es. comune.siena.it)
     * @return array{spf: ?string, spf_policy: string, dmarc: ?string, dmarc_policy: string}
     */
    function checkEmailSecurityRecords(string $domain): array
    {
        $spfRaw = null;
        $spfPolicy = 'none';

        // 1. Controllo SPF sui record TXT del dominio
        $txtRecords = @dns_get_record($domain, DNS_TXT);
        if (is_array($txtRecords)) {
            foreach ($txtRecords as $record) {
                $txt = $record['txt'] ?? (isset($record['entries']) ? implode('', $record['entries']) : '');
                if (str_starts_with(trim(strtolower($txt)), 'v=spf1')) {
                    $spfRaw = trim($txt);

                    // Determina la policy di default finale (-all, ~all, ?all, +all)
                    if (preg_match('/([~\-?+])all/i', $spfRaw, $m)) {
                        $spfPolicy = match ($m[1]) {
                            '-' => 'hardfail (-all)',
                            '~' => 'softfail (~all)',
                            '?' => 'neutral (?all)',
                            '+' => 'pass (+all)',
                            default => 'unknown'
                        };
                    } else {
                        $spfPolicy = 'missing_all';
                    }
                    break;
                }
            }
        }

        $dmarcRaw = null;
        $dmarcPolicy = 'none';

        // 2. Controllo DMARC interrogando la sottozona standard _dmarc.<dominio>
        $dmarcDomain = '_dmarc.' . $domain;
        $dmarcRecords = @dns_get_record($dmarcDomain, DNS_TXT);
        if (is_array($dmarcRecords)) {
            foreach ($dmarcRecords as $record) {
                $txt = $record['txt'] ?? (isset($record['entries']) ? implode('', $record['entries']) : '');
                if (str_starts_with(trim(strtolower($txt)), 'v=dmarc1')) {
                    $dmarcRaw = trim($txt);

                    // Estrazione della direttiva p=none|quarantine|reject
                    if (preg_match('/(?:^|;)\s*p\s*=\s*([^;]+)/i', $dmarcRaw, $matches)) {
                        $dmarcPolicy = strtolower(trim($matches[1]));
                    } else {
                        $dmarcPolicy = 'unspecified';
                    }
                    break;
                }
            }
        }

        return [
            'spf'          => $spfRaw,
            'spf_policy'   => $spfPolicy,
            'dmarc'        => $dmarcRaw,
            'dmarc_policy' => $dmarcPolicy,
        ];
    }

    $db = Database::getInstance();

    // Contatori per le statistiche giornaliere
    $totalProcessed = 0;
    $healthyCount   = 0;

    // Fetch 20 least recently checked or random entities
    $sql = "SELECT Codice_IPA
            FROM Enti
            WHERE LENGTH(Sito_istituzionale) > 1
              AND (Data_controllo IS NULL OR Data_controllo < NOW() - INTERVAL 7 DAY)
            ORDER BY (Data_controllo IS NOT NULL) ASC, Data_controllo ASC
            LIMIT 20";

    $stmt = $db->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $codiceIpa = $row['Codice_IPA'];
        $ente      = new Ente();

        if (!$ente->load($codiceIpa)) {
            echo "[{$codiceIpa}] Ente record not found.\n";
            continue;
        }

        $totalProcessed++;
        $logger?->info("[{$codiceIpa}] Start checking");

        // Fetch current stored tags for state change history logging
        $oldError   = $ente->getTag('validation_error_website');
        $oldTitle   = $ente->getTag('website_title');
        $oldHosting = $ente->getTag('hosting_provider');
        $oldMx      = $ente->getTag('mx_tag');

        $domain = extractHost($ente->sitoIstituzionale ?? $ente->sito_istituzionale ?? '');
        if (!$domain) {
            $logger?->error("[{$codiceIpa}] Invalid or missing URL");
            $newError = 'Sito web assente o URL non valido';
            echo "[{$codiceIpa}] have no website\n";

            if ($oldError !== $newError) {
                logStateChange($db, $codiceIpa, 'status_change', $oldError ?? 'Sito Online', $newError);
            }
            $ente->setTag('validation_error_website', $newError);

            // Always update Ente check date to mark it as inspected
            $sqlUpdate = 'UPDATE Enti SET Data_controllo = NOW() WHERE Codice_IPA = :codice_ipa';
            $sthUpdate = $db->prepare($sqlUpdate);
            $sthUpdate->execute([
                'codice_ipa' => $codiceIpa,
            ]);

            continue;
        }

        echo "[{$codiceIpa}] Checking domain: {$domain}\n";

        // --- 1. RUNTIME WEBSITE & SSL HEALTH CHECK ---
        $health   = checkWebsiteHealth($domain);
        $newError = !$health['healthy'] ? $health['error'] : null;

        if ($oldError !== $newError) {
            logStateChange(
                $db,
                $codiceIpa,
                'status_change',
                $oldError ?? 'Sito Online (200 OK)',
                $newError ?? 'Sito Online (200 OK)'
            );
        }

        if (!$health['healthy']) {
            $ente->setTag('validation_error_website', substr($health['error'],0,32));
            $logger?->warning("[{$codiceIpa}] Website Health Check FAILED: {$health['error']}");

            // Se l'errore è un certificato non valido o scaduto, registra lo score zero
            if (!empty($health['tls'])) {
                $ente->setTag('tls_security_score', (string)$health['tls']['score']);
                $ente->setTag('ssl_invalid_or_expired', '1');
            }
        } else {
            $healthyCount++;
            $ente->setTag('validation_error_website', null);
            $ente->removeTag('ssl_invalid_or_expired');
            $logger?->info("[{$codiceIpa}] Website Health Check PASSED (HTTP {$health['http_code']})");

            // --- GESTIONE AUDIT TLS E SCORE DI SICUREZZA ---
            if (!empty($health['tls'])) {
                $tls = $health['tls'];

                // 1. Metadati primari TLS
                $ente->setTag('tls_security_score', (string)$tls['score']);
                if (!empty($tls['negotiated_version'])) {
                    $ente->setTag('tls_negotiated', (string)$tls['negotiated_version']);
                }

                // 2. Metadati scadenza e issuer del certificato
                if ($tls['days_remaining'] !== null) {
                    $ente->setTag('ssl_days_remaining', (string)$tls['days_remaining']);
                    $ente->setTag('ssl_valid_to', (string)$tls['valid_to']);
                    if (!empty($tls['issuer'])) {
                        $ente->setTag('ssl_issuer', (string)$tls['issuer']);
                    }

                    // Stato testuale sintattico per filtri e dashboard
                    if ($tls['days_remaining'] < 0) {
                        $ente->setTag('ssl_status', 'expired');
                    } elseif ($tls['days_remaining'] <= 14) {
                        $ente->setTag('ssl_status', 'expiring_critical');
                    } elseif ($tls['days_remaining'] <= 30) {
                        $ente->setTag('ssl_status', 'expiring_warning');
                    } else {
                        $ente->setTag('ssl_status', 'valid');
                    }
                }

                // 3. Allineamento Tag: protocolli deprecati
                if (in_array('deprecated_tls10_enabled', $tls['tags'], true)) {
                    $ente->setTag('deprecated_tls10_enabled', '1');
                } else {
                    $ente->removeTag('deprecated_tls10_enabled');
                }

                if (in_array('deprecated_tls11_enabled', $tls['tags'], true)) {
                    $ente->setTag('deprecated_tls11_enabled', '1');
                } else {
                    $ente->removeTag('deprecated_tls11_enabled');
                }

                // 4. Allineamento Tag: modernità e scadenze
                if (in_array('modern_tls_only', $tls['tags'], true)) {
                    $ente->setTag('modern_tls_only', '1');
                } else {
                    $ente->removeTag('modern_tls_only');
                }

                if (in_array('ssl_expiring_soon', $tls['tags'], true)) {
                    $ente->setTag('ssl_expiring_soon', '1');
                } else {
                    $ente->removeTag('ssl_expiring_soon');
                }

                $ente->removeTag('insecure_http_only');

                $logger?->info(sprintf(
                    "[%s] TLS Audit: Score %d/100 | Negoziato: %s | Scadenza: %s gg | Legacy: %s",
                    $codiceIpa,
                    $tls['score'],
                    $tls['negotiated_version'] ?? 'N/D',
                    $tls['days_remaining'] !== null ? (string)$tls['days_remaining'] : 'N/D',
                    empty($tls['deprecated_supported']) ? 'Nessuno' : implode(', ', $tls['deprecated_supported'])
                ));
            } else {
                // Il portale non dispone di HTTPS (solo HTTP in chiaro)
                $ente->setTag('tls_security_score', '0');
                $ente->setTag('insecure_http_only', '1');
                $ente->removeTag('deprecated_tls10_enabled');
                $ente->removeTag('deprecated_tls11_enabled');
                $ente->removeTag('modern_tls_only');
                $ente->removeTag('ssl_days_remaining');
                $ente->removeTag('ssl_status');
            }
        }

        // --- 2. HOSTING PROVIDER CHECK ---
        $resolvedIp = gethostbyname($domain);
        if (!empty($resolvedIp) && $resolvedIp !== $domain) {
            try {
                $newHosting = getHostingInfo($resolvedIp);
                if ($newHosting) {
                    if ($newHosting !== $oldHosting) {
                        logStateChange($db, $codiceIpa, 'hosting_change', $oldHosting, $newHosting);
                    }
                    $ente->setTag('hosting_provider', $newHosting);
                    $logger?->info("[{$codiceIpa}] Hosting detected: {$newHosting} (IP: {$resolvedIp})");
                }
            } catch (Throwable $e) {
                $logger?->error("[{$codiceIpa}] Hosting check error: " . $e->getMessage());
            }
        }

        // --- 3. MX RECORD CHECK ---
        try {
            $dnsRecords = dns_get_record($domain, DNS_MX);
            $mxTag      = false;
            $mxCount    = 0;

            if (is_array($dnsRecords)) {
                foreach ($dnsRecords as $entry) {
                    if (isset($entry['target'])) {
                        $mxServer = strtolower($entry['target']);

                        if (str_contains($mxServer, 'google.')) {
                            $mxTag = 'Google';
                            break;
                        }

                        if (str_contains($mxServer, 'outlook')) {
                            $mxTag = 'Outlook';
                            break;
                        }

                        $mxCount++;
                        $ente->setTag('mx_' . $mxCount, $mxServer);
                    }
                }
            }

            if (!$mxTag) {
                $mxTag = ($mxCount > 0) ? 'Other' : 'No MX';
            }

            if ($mxTag !== $oldMx) {
                logStateChange($db, $codiceIpa, 'mx_change', $oldMx, $mxTag);
            }

            $ente->setTag('mx_tag', $mxTag);
        } catch (Throwable $e) {
            $logger?->error("[{$codiceIpa}] MX lookup error: " . $e->getMessage());
        }

        // --- 3b. EMAIL ANTI-SPOOFING (SPF & DMARC) ---
        try {
            $emailSec = checkEmailSecurityRecords($domain);

            // Gestione Tag SPF
            $oldSpf = $ente->getTag('mail_spf_policy');
            if ($emailSec['spf_policy'] !== $oldSpf) {
                logStateChange($db, $codiceIpa, 'spf_policy_change', $oldSpf, $emailSec['spf_policy']);
            }
            $ente->setTag('mail_spf', $emailSec['spf'] ? '1' : '0');
            $ente->setTag('mail_spf_policy', $emailSec['spf_policy']);

            // Gestione Tag DMARC
            $oldDmarc = $ente->getTag('mail_dmarc_policy');
            if ($emailSec['dmarc_policy'] !== $oldDmarc) {
                logStateChange($db, $codiceIpa, 'dmarc_policy_change', $oldDmarc, $emailSec['dmarc_policy']);
            }
            $ente->setTag('mail_dmarc', $emailSec['dmarc'] ? '1' : '0');
            $ente->setTag('mail_dmarc_policy', $emailSec['dmarc_policy']);

            $logger?->info("[{$codiceIpa}] Email Security: SPF={$emailSec['spf_policy']}, DMARC={$emailSec['dmarc_policy']}");
        } catch (Throwable $e) {
            $logger?->error("[{$codiceIpa}] SPF/DMARC check error: " . $e->getMessage());
        }

        // --- 4. SUBDOMAIN DISCOVERY (Subfinder) ---
        $command = 'subfinder -d ' . escapeshellarg($domain) . ' -silent';
        $output  = shell_exec($command);

        if (is_string($output) && trim($output) !== '') {
            $subdomains = array_filter(array_map('trim', explode("\n", $output)));

            foreach ($subdomains as $sub) {
                $subDns = dns_get_record($sub, DNS_A);
                if (is_array($subDns) && count($subDns) > 0) {
                    $ips = array_column($subDns, 'ip');
                    $ipString = implode(';', array_filter($ips));

                    $sqlInsert = 'INSERT INTO Domains (URL, Codice_IPA, IP, Add_Date)
                                  VALUES (:url, :codice_ipa, :ip, NOW())
                                  ON DUPLICATE KEY UPDATE Upd_Date = NOW()';

                    $sth = $db->prepare($sqlInsert);
                    $sth->execute([
                        'codice_ipa' => $codiceIpa,
                        'url'        => $sub,
                        'ip'         => $ipString,
                    ]);
                }
            }
            $logger?->info("[{$codiceIpa}] " . count($subdomains) . ' subdomains found.');
        } else {
            $logger?->warning("[{$codiceIpa}] No subdomains found.");
        }

        // --- 5. SCREENSHOT CAPTURE ---
        if (!empty($resolvedIp) && $resolvedIp !== $domain && $health['healthy']) {
            $fileName = $codiceIpa . '.jpg';
            $localTempDir = sys_get_temp_dir() . '/chrome_tmp_'.$codiceIpa .'/'; // Directory temporanea
            $localTempFile = $localTempDir.$fileName; // File temporaneo dello screenshot

            $targetUrl = 'http://' . $domain;
            $timeoutSeconds = 15;

            try {
                // Stable, low-memory Chrome Headless command (Crash-proof for containers/LXC)
                $cmd = sprintf(
                        'timeout %d /usr/bin/google-chrome ' .
                        '--headless=new ' .
                        '--no-sandbox ' .
                        '--disable-setuid-sandbox ' .
                        '--disable-dev-shm-usage ' .
                        '--no-zygote ' .
                        '--renderer-process-limit=1 ' .
                        '--disable-gpu ' .
                        '--disable-software-rasterizer ' .
                        '--disable-extensions ' .
                        '--disable-crash-reporter ' .
                        '--disable-breakpad ' .
                        '--disable-background-networking ' .
                        '--disable-sync ' .
                        '--disable-translate ' .
                        '--disable-application-cache ' .
                        '--disk-cache-size=1 ' .
                        '--media-cache-size=1 ' .
                        '--user-data-dir=%s ' .
                        '--data-path=%s ' .
                        '--disk-cache-dir=%s/cache ' .
                        '--js-flags="--max-old-space-size=128" ' .
                        '--window-size=1280,800 ' .
                        '--hide-scrollbars ' .
                        '--screenshot=%s %s 2>&1',
                        $timeoutSeconds,
                        escapeshellarg($localTempDir),
                        escapeshellarg($localTempDir),
                        escapeshellarg($localTempDir),
                        escapeshellarg($localTempFile),
                        escapeshellarg($targetUrl)
                    );

                exec($cmd, $outputLines, $returnCode);

                if ($returnCode === 0 && file_exists($localTempFile) && filesize($localTempFile) > 0) {
                    $remoteKey     = 'screenshots/' . $fileName;

                    try {
                        // Upload con sovrascrittura diretta
                        $result = $s3->putObject([
                            'Bucket'      => $r2Config['bucketName'],
                            'Key'         => $remoteKey,
                            'SourceFile'  => $localTempFile,
                            'ContentType' => 'image/jpeg',
                            'CacheControl'=> 'public, max-age=86400', // Cache 24h nel browser
                        ]);

                        $httpStatus = (int)($result['@metadata']['statusCode'] ?? 0);
                        $etag = trim((string)($result['ETag'] ?? ''), '"');

                        if ($httpStatus === 200) {
                            // Upload confermato: possiamo eliminare il file temporaneo locale
                            @unlink($localTempFile);

                            $ente->setTag('screenshot', $fileName);
                            $ente->setTag('screenshot_ts', date('Y-m-d H:i:s'));
                            $ente->setTag('screenshot_etag', $etag); // Utile opzionalmente come checksum

                            $logger?->info("[{$codiceIpa}] Screenshot done and uploaded (ETag: {$etag})");
                        }
                    } catch (\Aws\Exception\AwsException $e) {
                        $logger?->error("[{$codiceIpa}] Error while upload to R2: " . $e->getAwsErrorMessage() . " (HTTP " . $e->getStatusCode() . ")");
                    }
                } else {
                    $errorDetails = implode(' ', $outputLines);
                    $logger?->warning("[{$codiceIpa}] Screenshot failed or timed out (Exit Code {$returnCode}): {$errorDetails}");
                    $ente->setTag('screenshot', '');
                }

            } catch (Throwable $e) {
                $logger?->error("[{$codiceIpa}] Screenshot exception: " . $e->getMessage());
                $ente->setTag('screenshot', 'none.jpg');
            } finally {
                @shell_exec('pkill -f -9 "google-chrome.*--headless" 2>/dev/null');
                // 3. Rimozione forzata della directory temporanea (anche se timeout ha ucciso Chrome)
                if (is_dir($localTempDir)) {
                    exec('rm -rf ' . escapeshellarg($localTempDir));
                }
            }
        } else {
            $logger?->warning("[{$codiceIpa}] Domain host does not resolve or site is offline. Skipping screenshot.");
            $ente->setTag('screenshot', 'none.jpg');
        }

        // --- 6. WHATWEB SCANNER (CMS, PHP & HTTP HEADERS) ---
        if ($health['healthy']) {
            try {
                $tmpFile = tempnam(sys_get_temp_dir(), 'whatweb_');

                // Aggiunto HttpHeaders per il rilevamento delle intestazioni di sicurezza
                $plugins = 'Title,Email,PHP,HTTPServer,WordPress,Joomla,Drupal,TYPO3,Plone,OpenCMS,Liferay,Magento,PrestaShop';

                $execCmd = sprintf(
                    'timeout 30 whatweb %s -U %s -p %s --follow-redirect=always --log-json=%s 2>/dev/null',
                    escapeshellarg($domain),
                    escapeshellarg(BOT_USER_AGENT),
                    escapeshellarg($plugins),
                    escapeshellarg($tmpFile)
                );

                exec($execCmd);

                if (file_exists($tmpFile)) {
                    $content = file_get_contents($tmpFile);
                    @unlink($tmpFile);

                    if ($content) {
                        $data = json_decode($content, true);

                        if (is_array($data) && count($data) > 0) {
                            $site = end($data);
                            $detectedPlugins = $site['plugins'] ?? [];

                            // 1. Titolo della Pagina
                            $rawTitle = $detectedPlugins['Title']['string'] ?? null;

                            if (is_array($rawTitle)) {
                                $rawTitle = end($rawTitle);
                            }

                            $pageTitle = null;

                            if (!empty($rawTitle)) {
                                $title = (string)$rawTitle;

                                // Decodifica entità HTML (anche ricorsiva per gestire doppie codifiche)
                                do {
                                    $prev = $title;
                                    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                } while ($title !== $prev && str_contains($title, '&'));

                                // Rimuove ritorni a capo/tabulazioni e collassa spazi multipli
                                $title = trim(preg_replace('/\s+/', ' ', $title));

                                // Rimuove eventuali tag HTML residui non chiusi o iniettati
                                $title = strip_tags($title);

                                if (in_array($title, ['—', '–', '-', '|', '::', '/'], true)) {
                                    $pageTitle = null; // Il sito non espone un vero titolo
                                } else if ($title !== '') {
                                    $pageTitle = $title;
                                } else {
                                    $pageTitle = null;
                                }
                            }

                            if ($pageTitle !== null) {
                                $oldTitle = $ente->getTag('website_title');
                                if ($oldTitle !== null && $oldTitle !== '' && $pageTitle !== $oldTitle) {
                                    logStateChange($db, $codiceIpa, 'title_change', $oldTitle, $pageTitle);
                                }
                                $ente->setTag('website_title', $pageTitle);
                            }

                            // 2. Email di contatto dal sito
                            $pageEmail = $detectedPlugins['Email']['string'][0] ?? null;
                            if ($pageEmail) {
                                $ente->setTag('website_email', (string)$pageEmail);
                            }

                            // 3. Rilevamento Versione PHP
                            $phpVersion = null;

                            if (isset($detectedPlugins['PHP'])) {
                                $rawVal = $detectedPlugins['PHP']['version'][0] ?? $detectedPlugins['PHP']['string'][0] ?? null;
                                if ($rawVal && preg_match('#(\d+(?:\.\d+)+)#', (string)$rawVal, $m)) {
                                    $phpVersion = $m[1];
                                }
                            } elseif (isset($detectedPlugins['HTTPServer']['string'])) {
                                foreach ($detectedPlugins['HTTPServer']['string'] as $serverString) {
                                    if (preg_match('#PHP/(\d+(?:\.\d+)+)#i', $serverString, $matches)) {
                                        $phpVersion = $matches[1];
                                        break;
                                    }
                                }
                            }

                            // Fallback opzionale sugli header HTTP reali se WhatWeb non ha rilevato nulla
                            if (!$phpVersion && isset($parsedHeaders['x-powered-by'])) {
                                if (preg_match('#PHP/(\d+(?:\.\d+)+)#i', $parsedHeaders['x-powered-by'], $matches)) {
                                    $phpVersion = $matches[1];
                                }
                            }

                            // Aggiorna lo stato solo se è stata isolata una versione valida
                            if ($phpVersion !== null) {
                                $oldPhp = $ente->getTag('php_version');
                                if ($phpVersion !== $oldPhp) {
                                    logStateChange($db, $codiceIpa, 'php_change', $oldPhp, $phpVersion);
                                }
                                $ente->setTag('php_version', $phpVersion);
                            }

                            // 4. Rilevamento CMS
                            $knownCmsList = [
                                'WordPress',
                                'Joomla',
                                'Drupal',
                                'TYPO3',
                                'Plone',
                                'OpenCMS',
                                'Liferay',
                                'Magento',
                                'PrestaShop'
                            ];

                            $detectedCms = null;
                            $detectedCmsVersion = null;

                            foreach ($knownCmsList as $cmsName) {
                                if (isset($detectedPlugins[$cmsName])) {
                                    $detectedCms = $cmsName;
                                    $detectedCmsVersion = $detectedPlugins[$cmsName]['version'][0] ?? null;
                                    break;
                                }
                            }

                            if ($detectedCms) {
                                $oldCms = $ente->getTag('cms');
                                $fullCmsString = $detectedCms . ($detectedCmsVersion ? ' ' . $detectedCmsVersion : '');

                                if ($fullCmsString !== $oldCms) {
                                    logStateChange($db, $codiceIpa, 'cms_change', $oldCms, $fullCmsString);
                                }

                                $ente->setTag('cms', $detectedCms);
                                if ($detectedCmsVersion) {
                                    $ente->setTag('cms_version', (string)$detectedCmsVersion);
                                }
                            }

                            $parsedHeaders = fetchHttpHeaders('https://' . $domain);
                            if (empty($parsedHeaders)) {
                                $parsedHeaders = fetchHttpHeaders('http://' . $domain);
                            }

                            // 3. Verifica i security headers
                            if (!empty($parsedHeaders)) {
                                $sec = checkSecurityHeaders($parsedHeaders);

                                $ente->setTag('sec_hsts', $sec['hsts'] ? '1' : '0');
                                $ente->setTag('sec_csp', $sec['csp'] ? '1' : '0');
                                $ente->setTag('sec_xfo', $sec['xfo'] ? '1' : '0');
                                $ente->setTag('sec_xxp', $sec['xxp'] ? '1' : '0');
                                $ente->setTag('sec_rp', $sec['rp'] ? '1' : '0');

                                $oldScore = $ente->getTag('security_score');
                                if ($oldScore !== null && $oldScore !== '' && $oldScore !== $sec['score']) {
                                    logStateChange($db, $codiceIpa, 'security_grade_change', $oldScore, $sec['score']);
                                }
                                $ente->setTag('security_score', $sec['score']);
                            } else {
                                $ente->setTag('security_score', 'F');
                            }

                            // 6. Rilevamento e Tagging Web Server da Header HTTP
                            $rawServer = $parsedHeaders['server'] ?? '';

                            if ($rawServer !== '') {
                                // Normalizza e pulisce duplicati, separatori multipli e token generici (es. "Web")
                                $cleanServerStr = cleanWebServer($rawServer);

                                if ($cleanServerStr !== null) {
                                    $serverInfo = parseWebServerHeader($cleanServerStr);

                                    if (!empty($serverInfo['name'])) {
                                        $oldServer = $ente->getTag('web_server');
                                        $newServer = $serverInfo['name'];

                                        if ($oldServer !== null && $oldServer !== '' && $oldServer !== $newServer) {
                                            logStateChange($db, $codiceIpa, 'web_server_change', $oldServer, $newServer);
                                        }

                                        $ente->setTag('web_server', $newServer);

                                        // Gestisce la versione: aggiorna se presente, oppure azzera il tag se rimossa/occultata
                                        if (!empty($serverInfo['version'])) {
                                            $ente->setTag('web_server_version', (string)$serverInfo['version']);
                                        } else {
                                            $ente->setTag('web_server_version', null);
                                        }
                                    }
                                }
                            }

                            // 7. Rilevamento e Tagging WAF Generico
                            $waf = detectGenericWaf($parsedHeaders);

                            if ($waf['has_waf']) {
                                $oldWaf = $ente->getTag('waf_provider');
                                $newWaf = (string)$waf['provider'];

                                if ($oldWaf !== $newWaf) {
                                    logStateChange($db, $codiceIpa, 'waf_change', $oldWaf, $newWaf);
                                }

                                $ente->setTag('has_waf', '1');
                                $ente->setTag('waf_provider', $newWaf);
                            } else {
                                $oldWaf = $ente->getTag('waf_provider');
                                if ($oldWaf !== null && $oldWaf !== '') {
                                    logStateChange($db, $codiceIpa, 'waf_removed', $oldWaf, '0');
                                }

                                $ente->setTag('has_waf', '0');
                                $ente->setTag('waf_provider', null);
                            }
                        }
                    }
                }

            } catch (Throwable $e) {
                $logger?->error("[{$codiceIpa}] Scanner execution error: " . $e->getMessage());
            }
        }

        // Always update Ente check date to mark it as inspected
        $sqlUpdate = 'UPDATE Enti SET Sito_istituzionale = :domain, Data_controllo = NOW() WHERE Codice_IPA = :codice_ipa';
        $sthUpdate = $db->prepare($sqlUpdate);
        $sthUpdate->execute([
            'codice_ipa' => $codiceIpa,
            'domain'     => $domain,
        ]);
    }

    // --- 7. AGGIORNAMENTO STATISTICHE GIORNALIERE ---
    if ($totalProcessed > 0) {
        $errorCount = $totalProcessed - $healthyCount;

        $sqlStat = 'INSERT INTO Daily_Stats (stat_date, scanned_count, healthy_count, error_count)
                    VALUES (CURDATE(), :scanned, :healthy, :errors)
                    ON DUPLICATE KEY UPDATE
                        scanned_count = scanned_count + VALUES(scanned_count),
                        healthy_count = healthy_count + VALUES(healthy_count),
                        error_count   = error_count + VALUES(error_count)';

        $sthStat = $db->prepare($sqlStat);
        $sthStat->execute([
            'scanned' => $totalProcessed,
            'healthy' => $healthyCount,
            'errors'  => $errorCount,
        ]);

        $logger?->info("Daily stats updated: scanned={$totalProcessed}, healthy={$healthyCount}, errors={$errorCount}");
    }

} finally {
    FileLocker::unlockFile($lockFile);
}
