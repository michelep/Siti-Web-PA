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

    <div class="row g-4">
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
