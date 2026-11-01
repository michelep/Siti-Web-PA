<?php

/**
 * Progetto: Siti Web PA
 * Script Cron per l'invio del resoconto giornaliero delle notifiche da Enti_History (Versione HTML)
 *
 * @package    SitiWebPA
 * @author     Michele Pinassi <o-zone@zerozone.it>
 * @copyright  Copyright (c) 2026 Michele Pinassi
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.inc.php';

$db = Database::getInstance();

// 1. Estrai gli eventi non ancora notificati da Enti_History
$sql = "SELECT h.id, h.Codice_IPA, e.Denominazione_ente, h.event_type, h.old_value, h.new_value, h.created_at
        FROM Enti_History h
        LEFT JOIN Enti e ON h.Codice_IPA = e.Codice_IPA
        WHERE h.notified = 0
        ORDER BY h.created_at DESC";

$stmt = $db->query($sql);
$pendingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendingEvents)) {
    echo "Nessun evento pendente da notificare.\n";
    exit(0);
}

$dateToday   = date('d/m/Y');
$totalEvents = count($pendingEvents);

// 2. Mappatura eventi, etichette e palette colori badge
$eventMeta = [
    'security_grade_change' => ['label' => 'Sicurezza HTTP', 'bg' => '#dc3545', 'color' => '#ffffff'],
    'cms_change'            => ['label' => 'CMS Modificato', 'bg' => '#0d6efd', 'color' => '#ffffff'],
    'php_change'            => ['label' => 'Versione PHP',   'bg' => '#6f42c1', 'color' => '#ffffff'],
    'web_server_change'     => ['label' => 'Web Server',     'bg' => '#0dcaf0', 'color' => '#055160'],
    'title_change'          => ['label' => 'Titolo Portale', 'bg' => '#6c757d', 'color' => '#ffffff'],
    'mail_mx_change'        => ['label' => 'Mail Provider',  'bg' => '#198754', 'color' => '#ffffff'],
];

// Statistiche di riepilogo per categoria
$statsByType = [];
foreach ($pendingEvents as $ev) {
    $type = $ev['event_type'];
    $statsByType[$type] = ($statsByType[$type] ?? 0) + 1;
}

// 3. Generazione Righe Tabella Eventi
$rowsHtml = '';
foreach ($pendingEvents as $idx => $event) {
    $time      = (new DateTimeImmutable($event['created_at']))->format('H:i');
    $enteName  = htmlspecialchars($event['Denominazione_ente'] ?: $event['Codice_IPA'] ?: 'Ente Sconosciuto', ENT_QUOTES, 'UTF-8');
    $codiceIpa = htmlspecialchars((string)$event['Codice_IPA'], ENT_QUOTES, 'UTF-8');
    $oldVal    = htmlspecialchars((string)($event['old_value'] ?: '— N/D'), ENT_QUOTES, 'UTF-8');
    $newVal    = htmlspecialchars((string)($event['new_value'] ?: '— N/D'), ENT_QUOTES, 'UTF-8');

    $typeKey   = $event['event_type'];
    $meta      = $eventMeta[$typeKey] ?? ['label' => strtoupper(str_replace('_', ' ', $typeKey)), 'bg' => '#e9ecef', 'color' => '#495057'];
    $bgColor   = ($idx % 2 === 0) ? '#ffffff' : '#f8f9fa';

    $rowsHtml .= "
    <tr style=\"background-color: {$bgColor}; border-bottom: 1px solid #dee2e6;\">
        <td style=\"padding: 12px 10px; font-size: 13px; color: #6c757d; font-family: monospace; white-space: nowrap;\">
            {$time}
        </td>
        <td style=\"padding: 12px 10px;\">
            <span style=\"display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 4px; background-color: {$meta['bg']}; color: {$meta['color']}; text-transform: uppercase;\">
                {$meta['label']}
            </span>
        </td>
        <td style=\"padding: 12px 10px;\">
            <div style=\"font-weight: 600; color: #212529; font-size: 14px;\">{$enteName}</div>
            <a href=\"https://pa.zerozone.it/details/{$codiceIpa}\" style=\"font-size: 12px; color: #0d6efd; text-decoration: none; font-family: monospace;\">
                Codice IPA: {$codiceIpa} &rarr;
            </a>
        </td>
        <td style=\"padding: 12px 10px; font-size: 13px;\">
            <div style=\"color: #dc3545; text-decoration: line-through; font-size: 12px;\">{$oldVal}</div>
            <div style=\"color: #198754; font-weight: 600; margin-top: 2px;\">{$newVal}</div>
        </td>
    </tr>";
}

// 4. Badge Statistiche Rapide
$summaryBadges = '';
foreach ($statsByType as $typeKey => $count) {
    $meta = $eventMeta[$typeKey] ?? ['label' => str_replace('_', ' ', $typeKey), 'bg' => '#6c757d', 'color' => '#ffffff'];
    $summaryBadges .= "
    <span style=\"display: inline-block; margin: 0 8px 8px 0; padding: 4px 10px; background-color: #f1f3f5; border: 1px solid #dee2e6; border-radius: 20px; font-size: 12px; color: #333;\">
        <strong style=\"color: #0d6efd;\">{$count}</strong> {$meta['label']}
    </span>";
}

// 5. Template HTML Completo
$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Giornaliero Siti Web PA</title>
</head>
<body style="margin: 0; padding: 20px 0; background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f6f9;">
        <tr>
            <td align="center">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="max-width: 680px; width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e9ecef;">

                    <!-- HEADER -->
                    <tr>
                        <td style="padding: 24px 30px; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: #ffffff;">
                            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td>
                                        <h1 style="margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.5px;">Siti Web PA &mdash; Monitoraggio Civico</h1>
                                        <p style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">Report Giornaliero Anomalie e Variazioni Tecniche</p>
                                    </td>
                                    <td align="right" style="vertical-align: middle;">
                                        <span style="background-color: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; white-space: nowrap;">
                                            {$dateToday}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- HERO METRIC / RIEPILOGO -->
                    <tr>
                        <td style="padding: 24px 30px; border-bottom: 1px solid #e9ecef; background-color: #ffffff;">
                            <div style="font-size: 15px; color: #495057; margin-bottom: 15px;">
                                Nelle ultime 24 ore sono state riscontrate <strong style="color: #212529;">{$totalEvents} modifiche di stato</strong> sugli enti monitorati:
                            </div>
                            <div>
                                {$summaryBadges}
                            </div>
                        </td>
                    </tr>

                    <!-- TABELLA DETTAGLIO EVENTI -->
                    <tr>
                        <td style="padding: 0;">
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                        <th align="left" style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600; width: 60px;">Ora</th>
                                        <th align="left" style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600; width: 130px;">Ambito</th>
                                        <th align="left" style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600;">Ente / Portale</th>
                                        <th align="left" style="padding: 10px 10px; font-size: 11px; text-transform: uppercase; color: #6c757d; font-weight: 600; width: 180px;">Transizione</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$rowsHtml}
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <!-- CALL TO ACTION / FOOTER -->
                    <tr>
                        <td style="padding: 25px 30px; background-color: #f8f9fa; border-top: 1px solid #e9ecef; text-align: center;">
                            <a href="https://pa.zerozone.it" style="display: inline-block; background-color: #0d6efd; color: #ffffff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                                Apri Dashboard Completa
                            </a>
                            <p style="margin: 20px 0 0 0; font-size: 12px; color: #adb5bd; line-height: 1.5;">
                                Generato automaticamente dal cronjob di Siti Web PA.<br>
                                Iniziativa indipendente di monitoraggio trasparente &bull; Licenza GPL-3.0
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

// 6. Header MIME multipart / HTML
$toAddress = 'o-zone@zerozone.it';
$subject   = sprintf('[SitiWebPA] Report Giornaliero (%d variazioni rilevate) - %s', $totalEvents, $dateToday);

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Siti Web PA <noreply@zerozone.it>',
    'Reply-To: o-zone@zerozone.it',
    'X-Mailer: PHP/' . phpversion(),
    'Auto-Submitted: auto-generated',
    'X-Auto-Response-Suppress: All'
];

$mailSent = @mail($toAddress, $subject, $htmlBody, implode("\r\n", $headers));

// 7. Aggiornamento flag notified se l'invio ha successo
if ($mailSent) {
    $ids = array_column($pendingEvents, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $updateSql  = "UPDATE Enti_History SET notified = 1 WHERE id IN ({$placeholders})";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute($ids);

    echo sprintf("Report HTML inviato con successo a %s (%d eventi archiviati).\n", $toAddress, $totalEvents);
} else {
    echo "Errore durante l'invio dell'email HTML di notifica.\n";
    exit(1);
}
