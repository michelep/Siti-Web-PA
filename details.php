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

require_once __DIR__ . '/common.inc.php';

$codiceIpa = isset($_GET['codice_ipa']) ? trim((string)$_GET['codice_ipa']) : '';

if ($codiceIpa === '') {
    header('Location: /');
    exit;
}

$db = Database::getInstance();
$ente = new Ente();

if (!$ente->load($codiceIpa)) {
    http_response_code(404);
    $gbTitle = 'Ente non trovato';
    require_once __DIR__ . '/header.inc.php';
    echo '<div class="container my-5"><div class="alert alert-danger">L\'ente richiesto (' . htmlspecialchars($codiceIpa, ENT_QUOTES, 'UTF-8') . ') non è presente in archivio.</div></div>';
    require_once __DIR__ . '/footer.inc.php';
    exit;
}

$gbTitle = $ente->denominazioneEnte . ' (' . $codiceIpa . ') - Dettaglio Monitoring';
require_once __DIR__ . '/header.inc.php';

// Tag & Metadata
$validationError = $ente->getTag('validation_error_website');
$hostingProvider = $ente->getTag('hosting_provider');
$websiteTitle    = $ente->getTag('website_title');
$mxTag           = $ente->getTag('mx_tag');
$screenshot      = $ente->getTag('screenshot');

// Tags Mail
$mail1     = $ente->getTag('mail1');
$mail1Type = strtolower((string)$ente->getTag('mail1_type'));
$mail2     = $ente->getTag('mail2');
$mail2Type = strtolower((string)$ente->getTag('mail2_type'));

// Data controllo
$lastCheckDate = '&mdash;';
if ($ente->dataControllo instanceof DateTimeInterface) {
    $lastCheckDate = $ente->dataControllo->format('d/m/Y H:i');
}

// URL del sito
$sitoRaw = (string)($ente->sitoIstituzionale ?: '');
$sitoUrl = '';
if ($sitoRaw !== '') {
    $sitoUrl = preg_match('#^https?://#i', $sitoRaw) ? $sitoRaw : 'http://' . $sitoRaw;
}
?>

<!-- Leaflet CSS for Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<main class="container my-4">
    <!-- BREADCRUMB & INTRO -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($codiceIpa, ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><?= htmlspecialchars($ente->denominazioneEnte, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted mb-0">
                <span class="badge bg-secondary me-2"><?= htmlspecialchars($ente->tipologia ?: 'Ente Pubblico', ENT_QUOTES, 'UTF-8') ?></span>
                <strong>Codice IPA:</strong> <?= htmlspecialchars($codiceIpa, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div>
            <?php if ($validationError): ?>
                <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Anomalia Rilevata</span>
            <?php else: ?>
                <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Stato Regolare</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- SCHEDA SITO WEB & MONITORING -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h2 class="h5 fw-bold mb-3"><i class="bi bi-globe me-2 text-primary"></i>Informazioni Web & Infrastruttura</h2>

                <table class="table table-borderless align-middle mb-0">
                    <tbody>
                        <tr>
                            <th scope="row" class="text-muted ps-0" style="width: 35%;">Sito Istituzionale:</th>
                            <td class="pe-0">
                                <?php if ($sitoUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($sitoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="fw-semibold text-decoration-none text-break">
                                        <?= htmlspecialchars($sitoRaw, ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">&mdash; Non specificato</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" class="text-muted ps-0">Titolo Pagina:</th>
                            <td class="pe-0 fw-medium text-break"><?= htmlspecialchars($websiteTitle ?: '&mdash;', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th scope="row" class="text-muted ps-0">Hosting Provider:</th>
                            <td class="pe-0"><span class="badge bg-light text-dark border"><?= htmlspecialchars($hostingProvider ?: 'Non rilevato', ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="text-muted ps-0">Server Email (MX):</th>
                            <td class="pe-0"><span class="badge bg-info text-dark"><?= htmlspecialchars($mxTag ?: 'N/D', ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="text-muted ps-0">Stato Controllo:</th>
                            <td class="pe-0">
                                <?php if ($validationError): ?>
                                    <span class="text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($validationError, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="text-success fw-semibold"><i class="bi bi-check2-circle me-1"></i>Nessun errore di risoluzione</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" class="text-muted ps-0">Ultimo Check:</th>
                            <td class="pe-0 text-muted"><?= $lastCheckDate ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ANTEPRIMA SCREENSHOT -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4 h-100 text-center">
                <h2 class="h5 fw-bold mb-3 text-start"><i class="bi bi-display me-2 text-primary"></i>Anteprima Homepage</h2>
                <div class="d-flex align-items-center justify-content-center bg-light rounded border overflow-hidden" style="min-height: 220px;">
                    <?php if ($screenshot && $screenshot !== 'none.jpg' && file_exists(__DIR__ . '/screenshots/' . $screenshot)): ?>
                        <img src="/screenshots/<?= htmlspecialchars($screenshot, ENT_QUOTES, 'UTF-8') ?>" alt="Screenshot <?= htmlspecialchars($codiceIpa, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded shadow-sm" style="max-height: 240px; object-fit: cover;">
                    <?php else: ?>
                        <div class="text-muted py-4">
                            <i class="bi bi-camera-video-off fs-1 d-block mb-2 text-secondary"></i>
                            <span>Nessuno screenshot disponibile</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SEZIONE EMAIL E CONTATTI DI POSTA -->
    <?php if ($mail1 || $mail2): ?>
    <div class="card border-0 shadow-sm p-4 mt-4">
        <h3 class="h5 fw-bold mb-3">
            <i class="bi bi-envelope-at me-2 text-primary"></i>Contatti di Posta Elettronica
        </h3>

        <div class="row g-3">
            <!-- MAIL 1 -->
            <?php if ($mail1): ?>
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block fw-semibold mb-1">Indirizzo Principale (Mail 1)</small>
                            <a href="mailto:<?= htmlspecialchars($mail1, ENT_QUOTES, 'UTF-8') ?>" class="text-break text-decoration-none fw-medium">
                                <?= htmlspecialchars($mail1, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                        <?php if ($mail1Type === 'pec'): ?>
                            <span class="badge bg-success text-white ms-2 px-2 py-1"><i class="bi bi-shield-check me-1"></i>PEC</span>
                        <?php else: ?>
                            <span class="badge bg-secondary text-white ms-2 px-2 py-1">Ordinaria</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- MAIL 2 -->
            <?php if ($mail2): ?>
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block fw-semibold mb-1">Indirizzo Secondario (Mail 2)</small>
                            <a href="mailto:<?= htmlspecialchars($mail2, ENT_QUOTES, 'UTF-8') ?>" class="text-break text-decoration-none fw-medium">
                                <?= htmlspecialchars($mail2, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                        <?php if ($mail2Type === 'pec'): ?>
                            <span class="badge bg-success text-white ms-2 px-2 py-1"><i class="bi bi-shield-check me-1"></i>PEC</span>
                        <?php else: ?>
                            <span class="badge bg-secondary text-white ms-2 px-2 py-1">Ordinaria</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- UBICAZIONE GEOGRAFICA (CAP / INDIRIZZO / MAPPA) IN details.php -->
    <?php if (!empty($ente->latitude) && !empty($ente->longitude)): ?>
    <div class="card border-0 shadow-sm p-4 mt-4">
        <h3 class="h5 fw-bold mb-3"><i class="bi bi-geo-alt me-2 text-primary"></i>Ubicazione Sede</h3>
        <p class="text-muted mb-3">
            <strong>Indirizzo:</strong> <?= htmlspecialchars($ente->indirizzo ?: 'N/D', ENT_QUOTES, 'UTF-8') ?> |
            <strong>CAP:</strong> <?= htmlspecialchars($ente->cap ?: 'N/D', ENT_QUOTES, 'UTF-8') ?> |
            <strong>Codice ISTAT:</strong> <?= htmlspecialchars($ente->codiceComuneIstat ?: 'N/D', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <!-- Passaggio dati tramite data-attributes -->
        <div id="detail-map"
             style="height: 300px; width: 100%; border-radius: 8px;"
             data-lat="<?= (float)$ente->latitude ?>"
             data-lng="<?= (float)$ente->longitude ?>"
             data-title="<?= htmlspecialchars($ente->denominazioneEnte, ENT_QUOTES, 'UTF-8') ?>"
             data-address="<?= htmlspecialchars($ente->indirizzo ?: 'Sede Istituzionale', ENT_QUOTES, 'UTF-8') ?>">
        </div>
    </div>
    <?php endif; ?>

    <!-- CRONOLOGIA EVENTI E MODIFICHE (Enti_History) -->
    <div class="card border-0 shadow-sm p-4 mt-4">
        <h3 class="h5 fw-bold mb-3">
            <i class="bi bi-clock-history me-2 text-primary"></i>Cronologia Eventi e Modifiche
        </h3>

        <ul class="list-group list-group-flush border-top">
        <?php
        $sqlLogs = "SELECT event_type, old_value, new_value, created_at
                    FROM Enti_History
                    WHERE Codice_IPA = :codice_ipa
                    ORDER BY created_at DESC
                    LIMIT 15";
        $sthLogs = $db->prepare($sqlLogs);
        $sthLogs->execute(['codice_ipa' => $codiceIpa]);
        $logs = $sthLogs->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($logs)):
            foreach ($logs as $log):
                $date = (new DateTimeImmutable($log['created_at']))->format('d/m/Y H:i');

                $badgeClass = match ($log['event_type']) {
                    'entry_added'    => 'bg-success text-white',
                    'entry_removed'  => 'bg-danger text-white',
                    'status_change'  => 'bg-warning text-dark',
                    'hosting_change' => 'bg-info text-dark',
                    'title_change'   => 'bg-secondary text-white',
                    'mx_change'      => 'bg-dark text-white',
                    default          => 'bg-primary text-white'
                };

                $eventLabel = match ($log['event_type']) {
                    'entry_added'    => 'Nuovo Ente Registrato',
                    'entry_removed'  => 'Ente Rimosso da IPA',
                    default          => str_replace('_', ' ', strtoupper($log['event_type']))
                };
                ?>
                <li class="list-group-item px-0 py-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-1">
                            <?= htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <small class="text-muted"><?= $date ?></small>
                    </div>
                    <div class="small mt-2">
                        <?php if ($log['old_value']): ?>
                            <span class="text-decoration-line-through text-muted me-2"><?= htmlspecialchars($log['old_value'], ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="bi bi-arrow-right text-muted me-2"></i>
                        <?php endif; ?>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($log['new_value'] ?: 'Nessun valore', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </li>
            <?php endforeach;
        else: ?>
            <li class="list-group-item px-0 py-3 text-muted">
                <i class="bi bi-info-circle me-2"></i>Nessun cambio di stato o evento registrato di recente.
            </li>
        <?php endif; ?>
        </ul>
    </div>
</main>

<!-- Leaflet JS (Inizializzazione Mappa Dettaglio) -->
<?php if (!empty($ente->latitude) && !empty($ente->longitude)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.inc.php'; ?>
