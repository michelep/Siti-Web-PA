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

/** @var Monolog\Logger|null $logger */
$logger = $GLOBALS['logger'] ?? null;

$jsonPath = __DIR__ . '/enti.json';

if (!file_exists($jsonPath)) {
    echo "Error: File 'enti.json' not found at {$jsonPath}\n";
    exit(1);
}

$jsonContent = file_get_contents($jsonPath);
if ($jsonContent === false) {
    echo "Error: Failed to read 'enti.json'\n";
    exit(1);
}

$data = json_decode($jsonContent, true);

if (!is_array($data) || empty($data['fields']) || empty($data['records'])) {
    echo "Error: Invalid JSON structure in 'enti.json'\n";
    exit(1);
}

// Mappatura posizione -> ID campo
$fieldMap = [];
foreach ($data['fields'] as $index => $field) {
    if (isset($field['id'])) {
        $fieldMap[$index] = $field['id'];
    }
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

$db = Database::getInstance();

// =========================================================================
// 1. PULIZIA PREVENTIVA TAG ANOMALIE
// =========================================================================
echo "Starting pre-import cleanup of validation tags...\n";
try {
    $cleanupSql = "DELETE FROM Tags WHERE tagName IN ('validation_error_website', 'validation_error_email')";
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
// 2. TRACCIAMENTO CICLO DI VITA (Aggiunti / Rimossi)
// =========================================================================
echo "Analyzing dataset differences for lifecycle logging...\n";

// Mappa degli enti attualmente presenti a database
$existingEnti = $db->query("SELECT Codice_IPA FROM Enti")->fetchAll(PDO::FETCH_COLUMN, 0);
$existingMap  = array_fill_keys($existingEnti, true);

// Estraggo tutti i Codice_IPA presenti nel file JSON in ingresso
$incomingMap = [];
foreach ($data['records'] as $record) {
    $recordData = [];
    foreach ($record as $index => $value) {
        if (isset($fieldMap[$index])) {
            $recordData[$fieldMap[$index]] = $value;
        }
    }
    $codIpa = trim((string)($recordData['Codice_IPA'] ?? ''));
    if ($codIpa !== '') {
        $incomingMap[$codIpa] = $recordData['Denominazione_ente'] ?? 'Nuovo Ente';
    }
}

// Identifica i nuovi enti (presenti nel JSON ma non a DB)
$newEntries = array_diff_key($incomingMap, $existingMap);

// Identifica gli enti rimossi (presenti a DB ma assenti nel nuovo JSON)
$removedEntries = array_diff_key($existingMap, $incomingMap);

// Registra i nuovi enti
if (!empty($newEntries)) {
    foreach ($newEntries as $codIpa => $denominazione) {
        logHistoryEvent($db, $codIpa, 'entry_added', null, (string)$denominazione);
    }
    echo sprintf("Logged %d new entities added to IPA.\n", count($newEntries));
}

// Registra gli enti rimossi e li cancella dal database
if (!empty($removedEntries)) {
    $placeholders = implode(',', array_fill(0, count($removedEntries), '?'));

    // Recupera la denominazione per il log
    $stmtNames = $db->prepare("SELECT Codice_IPA, Denominazione_ente FROM Enti WHERE Codice_IPA IN ($placeholders)");
    $stmtNames->execute(array_keys($removedEntries));

    while ($row = $stmtNames->fetch(PDO::FETCH_ASSOC)) {
        logHistoryEvent($db, $row['Codice_IPA'], 'entry_removed', $row['Denominazione_ente'], 'Rimosso da Indice IPA');
    }

    // Eliminazione dei record non più presenti
    $deleteSql = "DELETE FROM Enti WHERE Codice_IPA IN ($placeholders)";
    $deleteStmt = $db->prepare($deleteSql);
    $deleteStmt->execute(array_keys($removedEntries));

    echo sprintf("Logged and removed %d obsolete entities from DB.\n", count($removedEntries));
}

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

try {
    foreach ($data['records'] as $record) {
        $enteData = [];

        foreach ($record as $index => $value) {
            if (isset($fieldMap[$index])) {
                $enteData[$fieldMap[$index]] = $value;
            }
        }

        $codiceIpa = trim((string)($enteData['Codice_IPA'] ?? ''));
        if ($codiceIpa === '') {
            continue;
        }

        $sitoRaw  = trim((string)($enteData['Sito_istituzionale'] ?? ''));
        $emailRaw = trim((string)($enteData['Mail'] ?? $enteData['Email'] ?? ''));

        // Esegue i controlli sintattici statici
        $websiteError = validateStaticSyntax($sitoRaw, 'website');
        $emailError   = validateStaticSyntax($emailRaw, 'email');

        // Formattazione data aggiornamento
        $rawDate = $enteData['Data_aggiornamento'] ?? null;
        $formattedDate = null;
        if (!empty($rawDate)) {
            try {
                $date = new DateTimeImmutable((string)$rawDate);
                $formattedDate = $date->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $formattedDate = date('Y-m-d H:i:s');
            }
        }

        // Estrazione dei nuovi campi dal record del dataset IPA
        $cap = trim((string)($enteData['Cap'] ?? $enteData['CAP'] ?? ''));
        $indirizzo = trim((string)($enteData['Indirizzo'] ?? ''));
        $istatCode = trim((string)($enteData['Codice_comune_ISTAT'] ?? ''));

        $mail1Raw     = trim((string)($enteData['Mail1'] ?? $enteData['mail1'] ?? ''));
        $tipoMail1Raw = trim((string)($enteData['Tipo_Mail1'] ?? $enteData['tipo_mail1'] ?? ''));

        $mail2Raw     = trim((string)($enteData['Mail2'] ?? $enteData['mail2'] ?? ''));
        $tipoMail2Raw = trim((string)($enteData['Tipo_Mail2'] ?? $enteData['tipo_mail2'] ?? ''));

        $stmtEnte->execute([
            'codice_ipa'          => $codiceIpa,
            'denominazione_ente'  => $enteData['Denominazione_ente'] ?? '',
            'tipologia'           => $enteData['Tipologia'] ?? null,
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

        // Applicazione tag anomalie sintattiche
        if ($websiteError !== null) {
            $enteObj->setTag('validation_error_website', $websiteError);
            $validationIssueCount++;
        }

        // --- GESTIONE MAIL 1 ---
        if ($mail1Raw !== '') {
            $mail1Error = validateStaticSyntax($mail1Raw, 'email');
            if ($mail1Error === null) {
                $enteObj->setTag('mail1', strtolower($mail1Raw));
                if ($tipoMail1Raw !== '') {
                    $enteObj->setTag('mail1_type', strtolower($tipoMail1Raw)); // es. 'pec' o 'altro'
                }
            } else {
                $enteObj->setTag('validation_error_email1', $mail1Error);
                $validationIssueCount++;
            }
        } else {
            // Rimuove eventuali tag obsoleti se l'email è stata rimossa dall'IPA
            $enteObj->removeTag('mail1');
            $enteObj->removeTag('mail1_type');
        }

        // --- GESTIONE MAIL 2 ---
        if ($mail2Raw !== '') {
            $mail2Error = validateStaticSyntax($mail2Raw, 'email');
            if ($mail2Error === null) {
                $enteObj->setTag('mail2', strtolower($mail2Raw));
                if ($tipoMail2Raw !== '') {
                    $enteObj->setTag('mail2_type', strtolower($tipoMail2Raw)); // es. 'pec' o 'altro'
                }
            } else {
                $enteObj->setTag('validation_error_email2', $mail2Error);
                $validationIssueCount++;
            }
        } else {
            // Rimuove eventuali tag obsoleti
            $enteObj->setTag('mail2', null);
            $enteObj->setTag('mail2_type', null);
        }

        $importedCount++;
    }

    $db->commit();
    echo "Import finished successfully!\n";
    echo "- Processed records: {$importedCount}\n";
    echo "- Fresh dataset anomalies tagged: {$validationIssueCount}\n";

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Fatal Import Error (Transaction rolled back): " . $e->getMessage() . "\n";
    $logger?->critical("[IMPORT] Failed: " . $e->getMessage());
    exit(1);
}
