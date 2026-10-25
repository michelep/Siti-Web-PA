<?php

/**
 * Progetto: Siti Web PA
 * Script Cron per l'invio del resoconto giornaliero delle notifiche da Enti_History
 *
 * @package   SitiWebPA
 * @author    Michele Pinassi <o-zone@zerozone.it>
 * @copyright Copyright (c) 2026 Michele Pinassi
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.inc.php';

$db = Database::getInstance();

// 1. Estrai gli eventi non ancora notificati da Enti_History
$sql = "SELECT h.id, h.Codice_IPA, e.Denominazione_ente, h.event_type, h.old_value, h.new_value, h.created_at
        FROM Enti_History h
        LEFT JOIN Enti e ON h.Codice_IPA = e.Codice_IPA
        WHERE h.notified = 0
        ORDER BY h.created_at ASC";

$stmt = $db->query($sql);
$pendingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendingEvents)) {
    echo "Nessun evento pendente da notificare.\n";
    exit(0);
}

// 2. Costruzione del testo dell'email
$dateToday   = date('d/m/Y');
$totalEvents = count($pendingEvents);

$messageBody = "=== RESOCONTO GIORNALIERO SITI WEB PA ({$dateToday}) ===\n";
$messageBody .= "Totale anomalie e modifiche rilevate nelle ultime 24 ore: {$totalEvents}\n\n";

foreach ($pendingEvents as $event) {
    $time      = (new DateTimeImmutable($event['created_at']))->format('H:i');
    $enteName  = $event['Denominazione_ente'] ?: $event['Codice_IPA'] ?: 'Ente Sconosciuto';
    $eventType = strtoupper(str_replace('_', ' ', $event['event_type']));

    $messageBody .= sprintf("[%s] %s (%s)\n", $time, $enteName, $event['Codice_IPA']);
    $messageBody .= sprintf("    Tipo Evento: %s\n", $eventType);

    if ($event['old_value']) {
        $messageBody .= sprintf("    Precedente : %s\n", $event['old_value']);
    }
    $messageBody .= sprintf("    Nuovo Stato: %s\n", $event['new_value'] ?: 'N/D');
    $messageBody .= "--------------------------------------------------\n";
}

// 3. Configurazione ed invio Email
$toAddress = 'o-zone@zerozone.it'; // Personalizza con la tua email
$subject   = sprintf('[SitiWebPA] Report Giornaliero Modifiche (%d eventi)', $totalEvents);
$headers   = [
    'From: SitiWebPA Monitor <noreply@zerozone.it>',
    'X-Mailer: PHP/' . phpversion(),
    'Content-Type: text/plain; charset=UTF-8'
];

$mailSent = @mail($toAddress, $subject, $messageBody, implode("\r\n", $headers));

// 4. Se l'invio è riuscito, segna gli eventi come notificati
if ($mailSent) {
    $ids = array_column($pendingEvents, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $updateSql  = "UPDATE Enti_History SET notified = 1 WHERE id IN ({$placeholders})";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute($ids);

    echo sprintf("Report inviato con successo a %s (%d eventi notificati).\n", $toAddress, $totalEvents);
} else {
    echo "Errore durante l'invio dell'email di notifica.\n";
    exit(1);
}
