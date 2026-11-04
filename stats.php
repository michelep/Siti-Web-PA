<?php

/**
 * Progetto: Faro PA
 * Pagina Statistiche e Riepilogo Metriche Aggregate
 *
 * @package    FaroPA
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/common.inc.php';

$db = Database::getInstance();

// 1. Metriche globali di perimetro
$totaleEnti = (int)$db->query("SELECT COUNT(*) FROM Enti")->fetchColumn();
$totaleSiti = (int)$db->query("SELECT COUNT(*) FROM Enti WHERE LENGTH(Sito_istituzionale) > 1")->fetchColumn();

/// 1. Estrazione dei conteggi aggregati per Codice_categoria
$stmtCat = $db->query("
    SELECT
        COALESCE(Codice_categoria, '') AS codice_cat,
        COUNT(*) AS totale
    FROM Enti
    WHERE LENGTH(Sito_istituzionale) > 1
    GROUP BY COALESCE(Codice_categoria, '')
");
$rawCategoryCounts = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// 2. Raggruppamento dinamico nelle macro-categorie tramite mapCodiceCategoria()
$aggregatedTypes = [];
$totaleEntiCategorizzati = 0;

foreach ($rawCategoryCounts as $row) {
    $code  = (string)$row['codice_cat'];
    $count = (int)$row['totale'];
    $totaleEntiCategorizzati += $count;

    // Richiama la funzione di mappatura per ottenere slug, label, icona e badge
    $meta = mapCodiceCategoria($code);
    $slug = $meta['slug'];

    if (!isset($aggregatedTypes[$slug])) {
        $aggregatedTypes[$slug] = [
            'slug'        => $slug,
            'label'       => $meta['label'],
            'icon'        => $meta['icon'],
            'badge_class' => $meta['badge_class'],
            'codes'       => [],
            'count'       => 0,
        ];
    }

    $aggregatedTypes[$slug]['count'] += $count;
    if ($code !== '') {
        $aggregatedTypes[$slug]['codes'][] = $code;
    }
}

// Ordina le categorie per numero decrescente di enti
uasort($aggregatedTypes, fn($a, $b) => $b['count'] <=> $a['count']);

// Mappa colore barra di avanzamento coerente con i badge
$barColors = [
    'comune'               => 'bg-primary',
    'scuola'               => 'bg-success',
    'ordine_professionale' => 'bg-warning',
    'sanita'               => 'bg-danger',
    'provincia'            => 'bg-info',
    'regione'              => 'bg-dark',
    'universita'           => 'bg-warning',
    'cciaa'                => 'bg-secondary',
    'centrale'             => 'bg-dark',
    'gestore_servizi'      => 'bg-secondary',
    'altro'                => 'bg-secondary',
];
// Helper per estrarre conteggi aggregati per chiave metadato (da tabella Enti_Tags o equivalente)
function getAggregateTagStats(PDO $db, string $tagKey, int $limit = 10): array
{
    $stmt = $db->prepare("
        SELECT tagValue AS name, COUNT(*) AS count
        FROM Tags
        WHERE tagName = :key AND tagValue IS NOT NULL AND tagValue != ''
        GROUP BY tagValue
        ORDER BY count DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':key', $tagKey, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Estrazione distribuzioni aggregate
$cmsStats       = getAggregateTagStats($db, 'cms', 10);
$phpStats       = getAggregateTagStats($db, 'php_version', 8);
$serverStats    = getAggregateTagStats($db, 'web_server', 8);
$wafStats       = getAggregateTagStats($db, 'waf_provider', 8);
$secScoreStats  = getAggregateTagStats($db, 'security_score', 6);

$hostingStats   = getAggregateTagStats($db, 'hosting_provider', 8);
$mailStats      = getAggregateTagStats($db, 'mx_tag', 8);

// Estrazione adozione singoli Security Headers
$secHeaderStats = [];
$secKeys = [
    'sec_hsts' => 'Strict-Transport-Security (HSTS)',
    'sec_csp'  => 'Content-Security-Policy (CSP)',
    'sec_xfo'  => 'X-Frame-Options (XFO)',
    'sec_xxp'  => 'X-XSS-Protection',
    'sec_rp'   => 'Referrer-Policy'
];

foreach ($secKeys as $k => $label) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM Tags WHERE tagName = :k AND tagValue = '1'");
    $stmt->execute(['k' => $k]);
    $count = (int)$stmt->fetchColumn();
    $secHeaderStats[] = [
        'name'  => $label,
        'count' => $count
    ];
}

// 1. Perimetro complessivo
$stmtTot = $db->query("SELECT COUNT(*) FROM Enti WHERE LENGTH(Sito_istituzionale) > 1");
$totaleTarget = (int)$stmtTot->fetchColumn();

// 2. Enti analizzati nell'attuale ciclo (es. ultimi 7 giorni)
$stmtScan = $db->query("
    SELECT COUNT(*)
    FROM Enti
    WHERE LENGTH(Sito_istituzionale) > 1
      AND Data_controllo >= NOW() - INTERVAL 7 DAY
");
$scansionatiNelCiclo = (int)$stmtScan->fetchColumn();

// 3. Velocità reale: quanti enti sono stati scansionati nell'ultima ora (60 min)
$stmtRate = $db->query("
    SELECT COUNT(*)
    FROM Enti
    WHERE LENGTH(Sito_istituzionale) > 1
      AND Data_controllo >= NOW() - INTERVAL 1 HOUR
");
$ritmoOrarioReale = (int)$stmtRate->fetchColumn();

// Calcolo avanzamento percentuale
$percentualeProgresso = $totaleTarget > 0 ? round(($scansionatiNelCiclo / $totaleTarget) * 100, 1) : 0;
if ($percentualeProgresso > 100) {
    $percentualeProgresso = 100;
}

// Calcolo tempo stimato di completamento (ETA)
$entiMancanti = max(0, $totaleTarget - $scansionatiNelCiclo);
$stimaOre     = null;
$etaTesto     = 'In calcolo...';

if ($ritmoOrarioReale > 0 && $entiMancanti > 0) {
    $stimaOre = (int)ceil($entiMancanti / $ritmoOrarioReale);
    if ($stimaOre < 24) {
        $etaTesto = "~{$stimaOre} ore";
    } else {
        $giorni = round($stimaOre / 24, 1);
        $etaTesto = "~{$giorni} giorni";
    }
} elseif ($entiMancanti === 0) {
    $etaTesto = 'Ciclo completato';
}

require_once __DIR__ . '/header.inc.php';
?>

<div class="container py-4">
    <!-- HERO HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h2 fw-bold text-dark mb-1">
                <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Statistiche Aggregate
            </h1>
            <p class="text-muted mb-0">Riepilogo delle metriche tecnologiche, cloud, hosting e sicurezza rilevati sui portali pubblici.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                <i class="bi bi-globe me-1 text-primary"></i> <?= number_format($totaleSiti, 0, ',', '.') ?> siti analizzati
            </span>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <div>
                    <h6 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                        <span class="spinner-grow spinner-grow-sm text-primary" role="status" aria-hidden="true"></span>
                        Progressione Ciclo di Scansione
                    </h6>
                    <p class="text-muted small mb-0">
                        Avanzamento del monitoraggio continuo dell'infrastruttura sui siti della PA.
                    </p>
                </div>
                <div class="text-end mt-2 mt-md-0 d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border px-2 py-1 small" title="Velocità calcolata sugli ultimi 60 minuti">
                        <i class="bi bi-speedometer text-info me-1"></i><strong><?= number_format($ritmoOrarioReale, 0, ',', '.') ?></strong> enti/ora
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-1 fw-bold">
                        <?= number_format($percentualeProgresso, 1, ',', '.') ?>%
                    </span>
                </div>
            </div>

            <!-- BARRA PROGRESSO ANIMATA -->
            <div class="progress" style="height: 12px; background-color: #e9ecef;" role="progressbar"
                 aria-valuenow="<?= $percentualeProgresso ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                     style="width: <?= $percentualeProgresso ?>%;"></div>
            </div>

            <!-- DETTAGLI DINAMICI -->
            <div class="d-flex flex-wrap justify-content-between text-muted small mt-2 pt-1 border-top border-light">
                <div>
                    <i class="bi bi-check2-circle text-success me-1"></i>
                    Completati: <strong><?= number_format($scansionatiNelCiclo, 0, ',', '.') ?></strong>
                </div>
                <div>
                    <i class="bi bi-hourglass-split text-warning me-1"></i>
                    Rimanenti: <strong><?= number_format($entiMancanti, 0, ',', '.') ?></strong>
                </div>
                <div>
                    <i class="bi bi-clock-history text-primary me-1"></i>
                    Fine ciclo stimata: <strong><?= $etaTesto ?></strong>
                </div>
                <div>
                    <i class="bi bi-collection me-1"></i>
                    Target totale: <strong><?= number_format($totaleTarget, 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- TABELLA DISTRIBUZIONE PER TIPOLOGIA ENTE -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-diagram-2 text-primary me-2"></i>Distribuzione Censimento per Tipologia di Ente
                        </h6>
                        <small class="text-muted">Ripartizione del perimetro monitorato in base al campo <code>Codice_categoria</code> di IndicePA.</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 280px;">Tipologia Ente</th>
                                    <th>Codici IPA Associati</th>
                                    <th class="text-end" style="width: 140px;">Enti Monitorati</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale sul Totale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($aggregatedTypes)): ?>
                                    <?php foreach ($aggregatedTypes as $slug => $data):
                                        $cnt = $data['count'];
                                        $pct = $totaleEntiCategorizzati > 0 ? ($cnt / $totaleEntiCategorizzati) * 100 : 0;
                                        $barClass = $barColors[$slug] ?? 'bg-secondary';
                                        $codiciStr = !empty($data['codes']) ? implode(', ', array_unique($data['codes'])) : 'N.D.';
                                    ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="badge <?= $data['badge_class'] ?> px-2 py-1">
                                                    <i class="bi <?= $data['icon'] ?> me-1"></i><?= htmlspecialchars($data['label'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                Codici IPA: <code><?= htmlspecialchars($codiciStr, ENT_QUOTES, 'UTF-8') ?></code>
                                            </td>
                                            <td class="text-end fw-semibold text-dark">
                                                <?= number_format($cnt, 0, ',', '.') ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 8px;">
                                                        <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small fw-bold" style="min-width: 48px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Nessun dato di categoria censito.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. TABELLA HOSTING & CLOUD PROVIDER -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-cloud-arrow-up text-primary me-2"></i>Servizi di Hosting & Datacenter
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Provider / AS</th>
                                    <th class="text-end">Enti</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($hostingStats)): ?>
                                    <?php foreach ($hostingStats as $row):
                                        $pct = $totaleSiti > 0 ? ($row['count'] / $totaleSiti) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark">
                                                <i class="bi bi-server text-muted me-1"></i><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-end"><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 45px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Nessun dato di hosting disponibile</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. TABELLA PROVIDER EMAIL (MX) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-envelope-at text-success me-2"></i>Provider di Posta Elettronica (MX)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Fornitore Mail</th>
                                    <th class="text-end">Enti</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mailStats)): ?>
                                    <?php foreach ($mailStats as $row):
                                        $pct = $totaleEnti > 0 ? ($row['count'] / $totaleEnti) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark">
                                                <i class="bi bi-mailbox text-muted me-1"></i><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-end"><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 45px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Nessun dato sui provider mail disponibile</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. TABELLA CMS -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-window-stack text-info me-2"></i>Piattaforme CMS Rilevate
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Piattaforma</th>
                                    <th class="text-end">Enti</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($cmsStats)): ?>
                                    <?php foreach ($cmsStats as $row):
                                        $pct = $totaleSiti > 0 ? ($row['count'] / $totaleSiti) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 45px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Nessun dato disponibile</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. TABELLA WEB SERVER -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-hdd-network text-secondary me-2"></i>Web Server & Reverse Proxy
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Software</th>
                                    <th class="text-end">Enti</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($serverStats)): ?>
                                    <?php foreach ($serverStats as $row):
                                        $pct = $totaleSiti > 0 ? ($row['count'] / $totaleSiti) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 45px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Nessun dato disponibile</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. TABELLA VERSIONI PHP -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-filetype-php text-purple me-2" style="color: #6f42c1;"></i>Versioni PHP Rilevate
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Versione</th>
                                    <th class="text-end">Enti</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($phpStats)): ?>
                                    <?php foreach ($phpStats as $row):
                                        $pct = $totaleSiti > 0 ? ($row['count'] / $totaleSiti) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark">PHP <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar" style="background-color: #6f42c1; width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 45px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Nessun dato disponibile</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. TABELLA WAF RILEVATI -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-shield-check text-warning me-2"></i>Protezioni WAF Rilevate
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Provider WAF</th>
                                    <th class="text-end">Enti</th>
                                    <th class="text-end pe-3" style="width: 35%;">Percentuale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($wafStats)): ?>
                                    <?php foreach ($wafStats as $row):
                                        $pct = $totaleSiti > 0 ? ($row['count'] / $totaleSiti) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-dark"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                                            <td class="text-end pe-3">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 45px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Nessun dato WAF disponibile</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. POSTURA SECURITY HEADERS (FULL WIDTH) -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-lock-fill text-danger me-2"></i>Adozione Intestazioni di Sicurezza HTTP
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Intestazione HTTP</th>
                                    <th class="text-end">Implementato su</th>
                                    <th class="text-end pe-3" style="width: 40%;">Adozione Complessiva</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($secHeaderStats as $row):
                                    $pct = $totaleSiti > 0 ? ($row['count'] / $totaleSiti) * 100 : 0;
                                    $barColor = $pct > 60 ? 'bg-success' : ($pct > 25 ? 'bg-warning' : 'bg-danger');
                                ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td class="text-end fw-semibold"><?= number_format((int)$row['count'], 0, ',', '.') ?> enti</td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= round($pct, 1) ?>%;"></div>
                                                </div>
                                                <span class="small fw-bold" style="min-width: 50px;"><?= number_format($pct, 1, ',', '.') ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.inc.php'; ?>
