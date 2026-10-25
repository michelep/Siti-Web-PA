<?php

/**
 * Progetto: Siti Web PA
 * Monitoraggio e analisi dei siti web della Pubblica Amministrazione Italiana
 * Sincronizzazione automatica e condizionale del dataset ufficiale IndicePA
 *
 * @package   SitiWebPA
 * @author    Michele Pinassi <o-zone@zerozone.it>
 * @copyright Copyright (c) 2026 Michele Pinassi
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.inc.php';

/** @var Monolog\Logger|null $logger */
$logger = $GLOBALS['logger'] ?? null;

$stateFile = sys_get_temp_dir() . '/ipa_dataset_last_modified.txt';
$lockFile  = sys_get_temp_dir() . '/sync_ipa.lock';

// Controllo concorrenza con lock esclusivo non bloccante
$fpLock = fopen($lockFile, 'c');
if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "Un'altra istanza dello script di sincronizzazione è già in esecuzione.\n";
    exit(0);
}

/**
 * Validatore sintattico e di placeholder per i valori statici del dataset
 */
function validateStaticSyntax(?string $value, string $type = 'website'): ?string
{
    if ($value === null || trim($value) === '') {
        return $type === 'website' ? 'missing_website' : 'missing_email';
    }

    $clean = strtolower(trim($value));
    $placeholders = ['n.a.', 'n/a', 'none', 'test', '-', 'http://-', 'http://n.a.', 'noemail@'];

    foreach ($placeholders as $ph) {
        if ($clean === $ph || str_contains($clean, $ph)) {
            return $type === 'website' ? 'placeholder_website' : 'placeholder_email';
        }
    }

    if ($type === 'website') {
        $urlToCheck = preg_match('#^https?://#i', $clean) ? $clean : 'http://' . $clean;
        if (filter_var($urlToCheck, FILTER_VALIDATE_URL) === false) {
            return 'invalid_website_syntax';
        }
    } else {
        if (filter_var($clean, FILTER_VALIDATE_EMAIL) === false) {
            return 'invalid_email_syntax';
        }
    }

    return null;
}

/**
 * Registra un evento nella tabella Enti_History
 */
function logHistoryEvent(PDO $db, ?string $codiceIpa, string $eventType, ?string $oldValue, ?string $newValue): void
{
    $oldClean = trim((string)$oldValue);
    $newClean = trim((string)$newValue);

    if ($oldClean === $newClean && $oldClean !== '') {
        return;
    }

    $sql = 'INSERT INTO Enti_History (Codice_IPA, event_type, old_value, new_value, created_at)
            VALUES (:codice_ipa, :event_type, :old_value, :new_value, NOW())';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'codice_ipa' => $codiceIpa,
        'event_type' => $eventType,
        'old_value'  => $oldClean !== '' ? $oldClean : null,
        'new_value'  => $newClean !== '' ? $newClean : null,
    ]);
}

try {
    $force = in_array('--force', $argv ?? [], true);
    $packageApiUrl = 'https://indicepa.gov.it/ipa-dati/api/3/action/package_show?id=enti';
    $resourceId    = 'd09adf99-dc10-4349-8c53-27b1e5aa97b6';

    echo "[" . date('Y-m-d H:i:s') . "] Controllo aggiornamenti dataset IndicePA...\n";

    // 1. Chiamata ai metadati del package CKAN per verificare l'ultima modifica
    $ch = curl_init($packageApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SitiWebPA-Bot/2.0; +https://pa.zerozone.it/about; o-zone@zerozone.it)'
    ]);

    $pkgResponse = curl_exec($ch);
    $pkgHttp     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $lastModified = null;
    if ($pkgHttp === 200 && $pkgResponse) {
        $pkgData = json_decode($pkgResponse, true);
        if ($pkgData['success'] ?? false) {
            foreach ($pkgData['result']['resources'] as $res) {
                if (($res['id'] ?? '') === $resourceId || str_contains(strtolower($res['name'] ?? ''), 'enti')) {
                    $lastModified = $res['last_modified'] ?? $res['metadata_modified'] ?? null;
                    break;
                }
            }
            if (!$lastModified) {
                $lastModified = $pkgData['result']['metadata_modified'] ?? null;
            }
        }
    }

    $lastStored = file_exists($stateFile) ? trim((string)file_get_contents($stateFile)) : '';

    echo "Data ultima modifica remota : " . ($lastModified ?: 'N/D') . "\n";
    echo "Data ultimo sync locale     : " . ($lastStored ?: 'Nessuno') . "\n";

    if (!$force && $lastModified && $lastStored === $lastModified) {
        echo "Dataset già aggiornato. Nessuna operazione necessaria.\n";
        exit(0);
    }

    // 2. Download del dump JSON del datastore
    $dumpUrl = "https://indicepa.gov.it/ipa-dati/datastore/dump/{$resourceId}?format=json";
    echo "Scaricamento dataset da {$dumpUrl}...\n";

    $tmpJson = tempnam(sys_get_temp_dir(), 'ipa_dump_');
    $fpOut   = fopen($tmpJson, 'w+');

    $chDump = curl_init($dumpUrl);
    curl_setopt_array($chDump, [
        CURLOPT_FILE           => $fpOut,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SitiWebPA-Bot/2.0; +https://pa.zerozone.it/about; o-zone@zerozone.it)'
    ]);

    $dumpSuccess = curl_exec($chDump);
    $dumpHttp    = curl_getinfo($chDump, CURLINFO_RESPONSE_CODE);
    curl_close($chDump);
    fclose($fpOut);

    if (!$dumpSuccess || $dumpHttp !== 200) {
        @unlink($tmpJson);
        throw new RuntimeException("Download fallito con codice HTTP {$dumpHttp}");
    }

    $fileSizeMb = round(filesize($tmpJson) / (1024 * 1024), 2);
    echo "Download completato ({$fileSizeMb} MB). Lettura e parsing del JSON...\n";

    $jsonContent = file_get_contents($tmpJson);
    @unlink($tmpJson);

    $data = json_decode((string)$jsonContent, true);
    unset($jsonContent); // Libera subito la memoria occupata dalla stringa grezza

    if (!is_array($data) || empty($data['fields']) || empty($data['records'])) {
        throw new RuntimeException("Struttura JSON non valida o assenza di campi/record nel dump.");
    }

    // Mappatura posizione -> ID campo
    $fieldMap = [];
    foreach ($data['fields'] as $index => $field) {
        if (isset($field['id'])) {
            $fieldMap[$index] = $field['id'];
        }
    }

    $db = Database::getInstance();

    // =========================================================================
    // 3. PULIZIA PREVENTIVA TAG ANOMALIE
    // =========================================================================
    echo "Starting pre-import cleanup of validation tags...\n";
    try {
        $cleanupSql = "DELETE FROM Tags WHERE tagName IN ('validation_error_website', 'validation_error_email', 'validation_error_email1', 'validation_error_email2')";
        $cleanupStmt = $db->prepare($cleanupSql);
        $cleanupStmt->execute();
        $clearedCount = $cleanupStmt->rowCount();

        $logger?->info("[IMPORT] Pre-import cleanup deleted {$clearedCount} old validation tags.");
        echo "Pre-import cleanup completed: {$clearedCount} old error tags cleared.\n";
    } catch (Throwable $e) {
        echo "Warning: Failed to execute pre-import cleanup: " . $e->getMessage() . "\n";
        $logger?->error("[IMPORT] Cleanup error: " . $e->getMessage());
    }

    // =========================================================================
    // 4. TRACCIAMENTO CICLO DI VITA (Aggiunti / Rimossi)
    // =========================================================================
    echo "Analyzing dataset differences for lifecycle logging...\n";

    $existingEnti = $db->query("SELECT Codice_IPA FROM Enti")->fetchAll(PDO::FETCH_COLUMN, 0);
    $existingMap  = array_fill_keys($existingEnti, true);

    $incomingMap = [];
    foreach ($data['records'] as $record) {
        $recordData = [];
        foreach ($record as $index => $value) {
            if (isset($fieldMap[$index])) {
                $recordData[$fieldMap[$index]] = $value;
            }
        }
        $codIpa = trim((string)($recordData['Codice_IPA'] ?? $recordData['cod_amm'] ?? ''));
        if ($codIpa !== '') {
            $incomingMap[$codIpa] = $recordData['Denominazione_ente'] ?? $recordData['des_amm'] ?? 'Nuovo Ente';
        }
    }

    $newEntries     = array_diff_key($incomingMap, $existingMap);
    $removedEntries = array_diff_key($existingMap, $incomingMap);

    if (!empty($newEntries)) {
        foreach ($newEntries as $codIpa => $denominazione) {
            logHistoryEvent($db, $codIpa, 'entry_added', null, (string)$denominazione);
        }
        echo sprintf("Logged %d new entities added to IPA.\n", count($newEntries));
    }

    if (!empty($removedEntries)) {
        $placeholders = implode(',', array_fill(0, count($removedEntries), '?'));

        $stmtNames = $db->prepare("SELECT Codice_IPA, Denominazione_ente FROM Enti WHERE Codice_IPA IN ($placeholders)");
        $stmtNames->execute(array_keys($removedEntries));

        while ($row = $stmtNames->fetch(PDO::FETCH_ASSOC)) {
            logHistoryEvent($db, $row['Codice_IPA'], 'entry_removed', $row['Denominazione_ente'], 'Rimosso da Indice IPA');
        }

        $deleteSql = "DELETE FROM Enti WHERE Codice_IPA IN ($placeholders)";
        $deleteStmt = $db->prepare($deleteSql);
        $deleteStmt->execute(array_keys($removedEntries));

        echo sprintf("Logged and removed %d obsolete entities from DB.\n", count($removedEntries));
    }

    // =========================================================================
    // 5. UPSERT ENTI E VALIDAZIONE DATI
    // =========================================================================
    $sqlEnte = 'INSERT INTO Enti (
                    Codice_IPA,
                    Denominazione_ente,
                    Tipologia,
                    Codice_comune_ISTAT,
                    CAP,
                    Indirizzo,
                    latitude,
                    longitude,
                    Sito_istituzionale,
                    Data_aggiornamento,
                    Data_inserimento
                ) VALUES (
                    :codice_ipa,
                    :denominazione_ente,
                    :tipologia,
                    :codice_comune_istat,
                    :cap,
                    :indirizzo,
                    (SELECT latitude FROM Cap_Coordinates WHERE cap = :cap_lookup1 OR istat_code = :istat_lookup1 LIMIT 1),
                    (SELECT longitude FROM Cap_Coordinates WHERE cap = :cap_lookup2 OR istat_code = :istat_lookup2 LIMIT 1),
                    :sito_istituzionale,
                    :data_aggiornamento,
                    NOW()
                ) ON DUPLICATE KEY UPDATE
                    Denominazione_ente = VALUES(Denominazione_ente),
                    Tipologia = VALUES(Tipologia),
                    Codice_comune_ISTAT = VALUES(Codice_comune_ISTAT),
                    CAP = VALUES(CAP),
                    Indirizzo = VALUES(Indirizzo),
                    latitude = COALESCE(VALUES(latitude), latitude),
                    longitude = COALESCE(VALUES(longitude), longitude),
                    Sito_istituzionale = VALUES(Sito_istituzionale),
                    Data_aggiornamento = VALUES(Data_aggiornamento)';

    $stmtEnte = $db->prepare($sqlEnte);

    $importedCount = 0;
    $validationIssueCount = 0;

    $db->beginTransaction();

    foreach ($data['records'] as $record) {
        $enteData = [];
        foreach ($record as $index => $value) {
            if (isset($fieldMap[$index])) {
                $enteData[$fieldMap[$index]] = $value;
            }
        }

        $codiceIpa = trim((string)($enteData['Codice_IPA'] ?? $enteData['cod_amm'] ?? ''));
        if ($codiceIpa === '') {
            continue;
        }

        $sitoRaw  = trim((string)($enteData['Sito_istituzionale'] ?? $enteData['sito_istituzionale'] ?? ''));
        $websiteError = validateStaticSyntax($sitoRaw, 'website');

        $rawDate = $enteData['Data_aggiornamento'] ?? $enteData['data_aggiornamento'] ?? null;
        $formattedDate = null;
        if (!empty($rawDate)) {
            try {
                $date = new DateTimeImmutable((string)$rawDate);
                $formattedDate = $date->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $formattedDate = date('Y-m-d H:i:s');
            }
        }

        $cap       = trim((string)($enteData['Cap'] ?? $enteData['CAP'] ?? $enteData['cap'] ?? ''));
        $indirizzo = trim((string)($enteData['Indirizzo'] ?? $enteData['indirizzo'] ?? ''));
        $istatCode = trim((string)($enteData['Codice_comune_ISTAT'] ?? $enteData['cod_istat'] ?? ''));

        $mail1Raw     = trim((string)($enteData['Mail1'] ?? $enteData['mail1'] ?? ''));
        $tipoMail1Raw = trim((string)($enteData['Tipo_Mail1'] ?? $enteData['tipo_mail1'] ?? ''));

        $mail2Raw     = trim((string)($enteData['Mail2'] ?? $enteData['mail2'] ?? ''));
        $tipoMail2Raw = trim((string)($enteData['Tipo_Mail2'] ?? $enteData['tipo_mail2'] ?? ''));

        $stmtEnte->execute([
            'codice_ipa'          => $codiceIpa,
            'denominazione_ente'  => $enteData['Denominazione_ente'] ?? $enteData['des_amm'] ?? '',
            'tipologia'           => $enteData['Tipologia'] ?? $enteData['tipologia'] ?? null,
            'codice_comune_istat' => $istatCode !== '' ? $istatCode : null,
            'cap'                 => $cap !== '' ? $cap : null,
            'cap_lookup1'         => $cap !== '' ? $cap : 'INVALID_CAP',
            'cap_lookup2'         => $cap !== '' ? $cap : 'INVALID_CAP',
            'istat_lookup1'       => $istatCode !== '' ? $istatCode : 'INVALID_ISTAT',
            'istat_lookup2'       => $istatCode !== '' ? $istatCode : 'INVALID_ISTAT',
            'indirizzo'           => $indirizzo !== '' ? $indirizzo : null,
            'sito_istituzionale'  => strtolower($sitoRaw),
            'data_aggiornamento'  => $formattedDate,
        ]);

        $enteObj = new Ente();
        $enteObj->codiceIpa = $codiceIpa;

        if ($websiteError !== null) {
            $enteObj->setTag('validation_error_website', $websiteError);
            $validationIssueCount++;
        }

        // Gestione Mail 1
        if ($mail1Raw !== '') {
            $mail1Error = validateStaticSyntax($mail1Raw, 'email');
            if ($mail1Error === null) {
                $enteObj->setTag('mail1', strtolower($mail1Raw));
                if ($tipoMail1Raw !== '') {
                    $enteObj->setTag('mail1_type', strtolower($tipoMail1Raw));
                }
            } else {
                $enteObj->setTag('validation_error_email1', $mail1Error);
                $validationIssueCount++;
            }
        } else {
            $enteObj->removeTag('mail1');
            $enteObj->removeTag('mail1_type');
        }

        // Gestione Mail 2
        if ($mail2Raw !== '') {
            $mail2Error = validateStaticSyntax($mail2Raw, 'email');
            if ($mail2Error === null) {
                $enteObj->setTag('mail2', strtolower($mail2Raw));
                if ($tipoMail2Raw !== '') {
                    $enteObj->setTag('mail2_type', strtolower($tipoMail2Raw));
                }
            } else {
                $enteObj->setTag('validation_error_email2', $mail2Error);
                $validationIssueCount++;
            }
        } else {
            $enteObj->removeTag('mail2');
            $enteObj->removeTag('mail2_type');
        }

        $importedCount++;

        // Commit parziale ogni 2500 record per limitare la memoria transazionale di InnoDB
        if ($importedCount % 2500 === 0) {
            $db->commit();
            $db->beginTransaction();
            echo "Elaborati {$importedCount} record...\n";
        }
    }

    $db->commit();

    // Salva il nuovo timestamp solo ad importazione ultimata con successo
    if ($lastModified) {
        file_put_contents($stateFile, $lastModified);
    }

    echo "Import terminato con successo!\n";
    echo "- Record elaborati: {$importedCount}\n";
    echo "- Nuove anomalie sintattiche rilevate: {$validationIssueCount}\n";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Fatal Import Error (Transaction rolled back): " . $e->getMessage() . "\n";
    $logger?->critical("[IMPORT] Failed: " . $e->getMessage());
    exit(1);
} finally {
    if (is_resource($fpLock)) {
        flock($fpLock, LOCK_UN);
        fclose($fpLock);
        @unlink($lockFile);
    }
}
