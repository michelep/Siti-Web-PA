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
     * Perform deep website health check (DNS, HTTP Status, SSL validity)
     */
    function checkWebsiteHealth(string $domain): array
    {
        // 1. Check DNS A/AAAA records
        $dnsA = @dns_get_record($domain, DNS_A + DNS_AAAA);
        if (empty($dnsA)) {
            return [
                'healthy' => false,
                'error'   => 'DNS Error: Impossibile risolvere il dominio (A/AAAA assente)'
            ];
        }

        // 2. Perform cURL request (Try HTTPS first, fallback to HTTP)
        $urlsToTry = ['https://' . $domain, 'http://' . $domain];
        $lastError = null;

        foreach ($urlsToTry as $targetUrl) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $targetUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY         => true, // HEAD request
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 12,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT      => BOT_USER_AGENT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlErrNo = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Safe SSL Error Detection
            $isSslError = (
                (defined('CURLE_SSL_CONNECT_ERROR') && $curlErrNo === CURLE_SSL_CONNECT_ERROR) ||
                in_array($curlErrNo, [35, 51, 58, 59, 60, 77, 83], true) ||
                str_contains(strtolower($curlError), 'ssl') ||
                str_contains(strtolower($curlError), 'certificate')
            );

            if ($isSslError) {
                return [
                    'healthy' => false,
                    'error'   => 'Errore Certificato SSL/TLS non valido o scaduto'
                ];
            }

            // HTTP 2xx or 3xx -> Success
            if ($httpCode >= 200 && $httpCode < 400) {
                return [
                    'healthy'   => true,
                    'http_code' => $httpCode,
                    'protocol'  => parse_url($targetUrl, PHP_URL_SCHEME),
                    'error'     => null
                ];
            }

            // HTTP 4xx or 5xx -> Explicit server error code
            if ($httpCode >= 400) {
                $lastError = sprintf('Errore HTTP %d (%s)', $httpCode, $httpCode === 404 ? 'Pagina non trovata' : 'Errore Server');
            } elseif ($curlErrNo > 0) {
                $lastError = 'Connessione fallita: ' . ($curlError ?: 'Timeout o Host irraggiungibile');
            }
        }

        return [
            'healthy' => false,
            'error'   => $lastError ?? 'Sito web non raggiungibile'
        ];
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

    // Fetch 2 least recently checked or random entities
    $sql  = 'SELECT Codice_IPA FROM Enti ORDER BY Data_controllo ASC, RAND() LIMIT 2';
    $stmt = $db->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $codiceIpa = $row['Codice_IPA'];
        $ente      = new Ente();

        echo "Start analysis of $codiceIpa...\n";

        if (!$ente->load($codiceIpa)) {
            echo "[{$codiceIpa}] Valid Ente record not found.\n";
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

            if ($oldError !== $newError) {
                logStateChange($db, $codiceIpa, 'status_change', $oldError ?? 'Sito Online', $newError);
            }
            $ente->setTag('validation_error_website', $newError);
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
            $ente->setTag('validation_error_website', $health['error']);
            $logger?->warning("[{$codiceIpa}] Website Health Check FAILED: {$health['error']}");
        } else {
            $healthyCount++;
            $ente->setTag('validation_error_website', null);
            $logger?->info("[{$codiceIpa}] Website Health Check PASSED (HTTP {$health['http_code']})");
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
            try {
                $fileName  = $codiceIpa . '_' . date('Y-m-d') . '.jpg';
                $outputDir = __DIR__ . '/../screenshots/';
                $filePath  = $outputDir . $fileName;

                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                $targetUrl = 'http://' . $domain;
                $timeoutSeconds = 15;

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
                    '--js-flags="--max-old-space-size=128" ' .
                    '--window-size=1280,800 ' .
                    '--hide-scrollbars ' .
                    '--screenshot=%s %s 2>&1',
                    $timeoutSeconds,
                    escapeshellarg($filePath),
                    escapeshellarg($targetUrl)
                );

                exec($cmd, $outputLines, $returnCode);

                if ($returnCode === 0 && file_exists($filePath) && filesize($filePath) > 0) {
                    $ente->setTag('screenshot', $fileName);
                    $ente->setTag('screenshot_ts', date('Y-m-d'));
                    $logger?->info("[{$codiceIpa}] Screenshot completed: {$fileName}");
                } else {
                    $errorDetails = implode(' ', $outputLines);
                    $logger?->warning("[{$codiceIpa}] Screenshot failed or timed out (Exit Code {$returnCode}): {$errorDetails}");
                    $ente->setTag('screenshot', 'none.jpg');
                }

            } catch (Throwable $e) {
                $logger?->error("[{$codiceIpa}] Screenshot exception: " . $e->getMessage());
                $ente->setTag('screenshot', 'none.jpg');
            } finally {
                @shell_exec('pkill -f -9 "google-chrome.*--headless" 2>/dev/null');
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
                $plugins = 'Title,Email,PHP,HTTPServer,WordPress,Joomla,Drupal,TYPO3,Plone,OpenCMS,Liferay,Magento,PrestaShop,HttpHeaders';

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
                            $pageTitle = $detectedPlugins['Title']['string'][0] ?? null;
                            if ($pageTitle) {
                                $oldTitle = $ente->getTag('website_title');
                                if ($pageTitle !== $oldTitle) {
                                    logStateChange($db, $codiceIpa, 'title_change', $oldTitle, (string)$pageTitle);
                                }
                                $ente->setTag('website_title', (string)$pageTitle);
                            }

                            // 2. Email di contatto dal sito
                            $pageEmail = $detectedPlugins['Email']['string'][0] ?? null;
                            if ($pageEmail) {
                                $ente->setTag('website_email', (string)$pageEmail);
                            }

                            // 3. Rilevamento Versione PHP
                            $phpVersion = null;
                            if (isset($detectedPlugins['PHP'])) {
                                $phpVersion = $detectedPlugins['PHP']['version'][0] ?? $detectedPlugins['PHP']['string'][0] ?? 'Detected';
                            } elseif (isset($detectedPlugins['HTTPServer']['string'])) {
                                foreach ($detectedPlugins['HTTPServer']['string'] as $serverString) {
                                    if (preg_match('#PHP/([0-9\.]+)#i', $serverString, $matches)) {
                                        $phpVersion = $matches[1];
                                        break;
                                    }
                                }
                            }

                            if ($phpVersion) {
                                $oldPhp = $ente->getTag('php_version');
                                if ($phpVersion !== $oldPhp) {
                                    logStateChange($db, $codiceIpa, 'php_change', $oldPhp, (string)$phpVersion);
                                }
                                $ente->setTag('php_version', (string)$phpVersion);
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

                            // 5. Analisi Header di Sicurezza (HttpHeaders Plugin)
                            $rawHeaders = $detectedPlugins['HttpHeaders']['string'] ?? [];
                            $parsedHeaders = [];

                            foreach ($rawHeaders as $headerLine) {
                                if (str_contains($headerLine, ':')) {
                                    [$hKey, $hVal] = explode(':', $headerLine, 2);
                                    $parsedHeaders[trim($hKey)] = trim($hVal);
                                }
                            }

                            if (empty($parsedHeaders) && !empty($site['http_headers'])) {
                                foreach ($site['http_headers'] as $k => $v) {
                                    $parsedHeaders[(string)$k] = is_array($v) ? implode(', ', $v) : (string)$v;
                                }
                            }

                            if (!empty($parsedHeaders)) {
                                $sec = checkSecurityHeaders($parsedHeaders);

                                $ente->setTag('sec_hsts', $sec['hsts'] ? '1' : '0');
                                $ente->setTag('sec_csp', $sec['csp'] ? '1' : '0');
                                $ente->setTag('sec_xfo', $sec['xfo'] ? '1' : '0');
                                $ente->setTag('sec_xxp', $sec['xxp'] ? '1' : '0');
                                $ente->setTag('sec_rp', $sec['rp'] ? '1' : '0');

                                $oldScore = $ente->getTag('security_score');
                                if ($oldScore !== $sec['score']) {
                                    logStateChange($db, $codiceIpa, 'security_grade_change', $oldScore, $sec['score']);
                                }
                                $ente->setTag('security_score', $sec['score']);
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
