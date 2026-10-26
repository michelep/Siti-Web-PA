<?php

/**
 * Progetto: Siti Web PA
 * Aggiornamento periodico dei cicli di vita e delle versioni supportate da endoflife.date
 *
 * @package   SitiWebPA
 * @author    Michele Pinassi <o-zone@zerozone.it>
 * @copyright Copyright (c) 2026 Michele Pinassi
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.inc.php';

$db = Database::getInstance();

// Prodotti supportati da endoflife.date che monitoriamo
$products = [
    'php'       => 'php',
    'wordpress' => 'wordpress',
    'drupal'    => 'drupal',
    'joomla'    => 'joomla',
    'typo3'     => 'typo3'
];

$sqlUpsert = 'INSERT INTO Software_Lifecycle (product, cycle, latest_version, eol_date, is_supported, updated_at)
              VALUES (:product, :cycle, :latest_version, :eol_date, :is_supported, NOW())
              ON DUPLICATE KEY UPDATE
                  latest_version = VALUES(latest_version),
                  eol_date       = VALUES(eol_date),
                  is_supported   = VALUES(is_supported),
                  updated_at     = NOW()';

$stmt = $db->prepare($sqlUpsert);

foreach ($products as $localProduct => $apiProduct) {
    echo "Aggiornamento ciclo di vita per: {$localProduct}...\n";

    $url = "https://endoflife.date/api/{$apiProduct}.json";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'SitiWebPA-LifecycleSync/1.0 (+https://pa.zerozone.it/about)'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo "  [WARN] Impossibile recuperare dati per {$apiProduct} (HTTP {$httpCode})\n";
        continue;
    }

    $cycles = json_decode($response, true);
    if (!is_array($cycles)) {
        continue;
    }

    $today = date('Y-m-d');

    foreach ($cycles as $c) {
        $cycleName     = (string)($c['cycle'] ?? '');
        $latestVersion = (string)($c['latest'] ?? $cycleName);
        $eolRaw        = $c['eol'] ?? null;

        // EOL può essere un booleano (true/false) o una stringa data "YYYY-MM-DD"
        $isSupported = true;
        $eolDate = null;

        if (is_bool($eolRaw)) {
            $isSupported = !$eolRaw;
        } elseif (is_string($eolRaw)) {
            $eolDate = $eolRaw;
            $isSupported = ($eolRaw >= $today);
        }

        if ($cycleName !== '') {
            $stmt->execute([
                'product'        => $localProduct,
                'cycle'          => $cycleName,
                'latest_version' => $latestVersion,
                'eol_date'       => $eolDate,
                'is_supported'   => $isSupported ? 1 : 0,
            ]);
        }
    }
}

echo "Aggiornamento lifecycle completato.\n";
