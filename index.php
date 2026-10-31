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
require_once __DIR__ . '/header.inc.php';

?>
<!-- MAIN DASHBOARD -->
<main class="flex-shrink-0 my-4">
    <div class="container">

        <!-- DASHBOARD TITLE / HERO SUB-HEADER -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
            <div>
                <h2 class="h3 fw-bold mb-1">Panoramica Monitoraggio</h2>
                <p class="text-muted mb-0">Analisi della presenza e della configurazione tecnica dei portali istituzionali</p>
            </div>
            <div class="mt-3 mt-md-0">
                <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">
                    <i class="bi bi-clock-history me-1 text-primary"></i> Aggiornamento orario
                </span>
            </div>
        </div>

        <!-- METRIC SUMMARY CARDS -->
        <div class="row g-3 mb-4">
            <!-- 1. Totale Enti IPA -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat p-3 h-100 border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-building fs-4"></i>
                        </div>
                        <div>
                            <span class="text-uppercase text-muted fw-medium small">Totale Enti IPA</span>
                            <h3 class="fw-bold fs-2 mb-0 mt-1 text-dark" id="stat-totali">&mdash;</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Siti Istituzionali -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat p-3 h-100 border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-globe fs-4"></i>
                        </div>
                        <div>
                            <span class="text-uppercase text-muted fw-medium small">Siti Istituzionali</span>
                            <h3 class="fw-bold fs-2 mb-0 mt-1 text-dark" id="stat-siti">&mdash;</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Anomalie Dati -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat p-3 h-100 border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </div>
                        <div>
                            <span class="text-uppercase text-muted fw-medium small">Anomalie Dati</span>
                            <h3 class="fw-bold fs-2 mb-0 mt-1 text-dark" id="stat-errori">&mdash;</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Stato Scansioni (24h vs >72h) -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat p-3 h-100 border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-activity fs-4"></i>
                        </div>
                        <div class="w-100">
                            <span class="text-uppercase text-muted fw-medium small">Attività Scansione</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1">
                                <div>
                                    <h3 class="fw-bold fs-2 mb-0 text-success d-inline" id="stat-aggiornati-24h">&mdash;</h3>
                                    <span class="text-muted small ms-1">&le; 24h</span>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold fs-5 text-warning" id="stat-stale-72h">&mdash;</span>
                                    <span class="text-muted small ms-1">&gt; 72h</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DISTRIBUTION PIE CHARTS ROW -->
        <div class="row g-4 mb-4">
            <!-- Top 5 Hosting Providers Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h5 class="fw-bold fs-6 mb-3 text-dark">
                        <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Top 5 Hosting Provider
                    </h5>
                    <div style="position: relative; height: 260px;">
                        <canvas id="chart-hosting"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top 5 Mail Providers Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <h5 class="fw-bold fs-6 mb-3 text-dark">
                        <i class="bi bi-pie-chart-fill me-2 text-info"></i>Top 5 Mail Provider (MX)
                    </h5>
                    <div style="position: relative; height: 260px;">
                        <canvas id="chart-mail"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="row g-4 mt-2 mb-4">
                <!-- GRAFICO 1: Top 5 CMS -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm p-4 h-100 text-center">
                        <h3 class="h6 fw-bold mb-3 text-start">
                            <i class="bi bi-window-stack text-primary me-2"></i>Diffusione CMS (Top 5)
                        </h3>
                        <div style="position: relative; height: 260px;">
                            <canvas id="chart-cms"></canvas>
                        </div>
                    </div>
                </div>

                <!-- GRAFICO 2: Versioni PHP -->
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm p-4 h-100 text-center">
                        <h3 class="h6 fw-bold mb-3 text-start">
                            <i class="bi bi-cpu text-info me-2"></i>Versioni PHP Rilevate
                        </h3>
                        <div style="position: relative; height: 260px;">
                            <canvas id="chart-php"></canvas>
                        </div>
                    </div>
                </div>

                <!-- GRAFICO 3: Protezione Email (SPF vs DKIM) -->
                <div class="col-lg-4 col-md-12">
                    <div class="card border-0 shadow-sm p-4 h-100 text-center">
                        <h3 class="h6 fw-bold mb-3 text-start">
                            <i class="bi bi-shield-check text-success me-2"></i>Adozione SPF &amp; DKIM
                        </h3>
                        <div style="position: relative; height: 260px;">
                            <canvas id="chart-email-security"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT ENTI CARDS SECTION -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="h5 fw-bold mb-0"><i class="bi bi-camera me-2 text-primary"></i>Ultimi Verificati</h4>
                <a href="/screenshot" class="btn btn-sm btn-link text-decoration-none">Vedi tutti <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="row g-4">
            <?php
            $db = Database::getInstance();
            $sql = 'SELECT Codice_IPA FROM Enti WHERE Data_controllo IS NOT NULL ORDER BY Data_controllo DESC LIMIT 3';
            $sth = $db->prepare($sql);

            if ($sth->execute()) {
                while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                    $ente = new Ente();
                    if ($ente->load($row['Codice_IPA'])) {
                        $codiceIpa     = htmlspecialchars($ente->codiceIpa ?? '', ENT_QUOTES, 'UTF-8');
                        $screenshot    = htmlspecialchars($ente->getScreenshot(), ENT_QUOTES, 'UTF-8');
                        $title         = htmlspecialchars($ente->getTag('website_title') ?? 'Sito istituzionale', ENT_QUOTES, 'UTF-8');
                        $denominazione = htmlspecialchars($ente->denominazioneEnte ?? '', ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="col-md-4">
                            <div class="card card-preview h-100">
                                <a href="/details/<?= $codiceIpa ?>">
                                    <img class="card-img-top" src="<?= $screenshot ?>" alt="<?= $title ?>">
                                </a>
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <span class="badge bg-light text-secondary border mb-2"><?= $codiceIpa ?></span>
                                        <h6 class="card-title text-dark fw-semibold mb-1 text-truncate" title="<?= $denominazione ?>">
                                            <?= $denominazione ?>
                                        </h6>
                                    </div>
                                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><i class="bi bi-globe me-1"></i> Verified</small>
                                        <a href="/details/<?= $codiceIpa ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Dettagli</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
            }
            ?>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div>
            <h4 class="h5 fw-bold mb-3"><i class="bi bi-table me-2 text-primary"></i>Elenco Completo Enti</h4>
            <div class="table-card p-3">
                <table id="table"
                       data-toggle="table"
                       data-height="550"
                       data-pagination="true"
                       data-side-pagination="server"
                       data-search="true"
                       data-show-search-button="true"
                       data-search-highlight="true"
                       data-classes="table table-hover table-borderless align-middle mb-0"
                       data-url="/ajax/get_list">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th data-field="codice_ipa" data-sortable="true" data-width="120">Codice IPA</th>
                            <th data-field="denominazione" data-sortable="true">Denominazione Ente</th>
                            <th data-field="tipologia" data-sortable="true">Tipologia</th>
                            <th data-field="sito_web" data-width="250">Sito Web</th>
                            <th data-field="last_check" data-width="150">Ultimo controllo</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

    </div>
</main>
<!-- /MAIN -->
<?php
require_once __DIR__ . '/footer.inc.php';
?>
