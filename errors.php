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

$gbTitle = 'Anomalie Dati e Validazione IPA';
$gbDescription = 'Elenco degli Enti della Pubblica Amministrazione con anomalie nei dati (siti web o email non validi/assenti).';

require_once __DIR__ . '/common.inc.php';
require_once __DIR__ . '/header.inc.php';

$db = Database::getInstance();

// 1. Fetch breakdown counts by error tag
$sqlWebsiteErrors = "SELECT COUNT(DISTINCT Codice_IPA) FROM Tags WHERE tagName = 'validation_error_website'";
$totalWebsiteErrors = (int)$db->query($sqlWebsiteErrors)->fetchColumn();

$sqlEmailErrors = "SELECT COUNT(DISTINCT Codice_IPA) FROM Tags WHERE tagName = 'validation_error_email'";
$totalEmailErrors = (int)$db->query($sqlEmailErrors)->fetchColumn();

$sqlTotalUniqueErrors = "SELECT COUNT(DISTINCT Codice_IPA) FROM Tags WHERE tagName IN ('validation_error_website', 'validation_error_email')";
$totalUniqueErrors = (int)$db->query($sqlTotalUniqueErrors)->fetchColumn();

?>
<!-- MAIN CONTENT -->
<main class="flex-shrink-0 my-4">
    <div class="container">

        <!-- BREADCRUMB NAV -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><i class="bi bi-house me-1"></i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Anomalie Dati</li>
            </ol>
        </nav>

        <!-- PAGE HEADER -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
            <div>
                <h2 class="h3 fw-bold mb-1 text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Report Anomalie Dati
                </h2>
                <p class="text-muted mb-0">Rilevamento automatico di formattazioni errate, siti web non raggiungibili o email mancanti nei dati IPA</p>
            </div>
            <div class="mt-3 mt-md-0">
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill">
                    Totale Enti Flaggati: <?= number_format($totalUniqueErrors, 0, ',', '.') ?>
                </span>
            </div>
        </div>

        <!-- BREAKDOWN STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-stat p-3 h-100 border-warning border-opacity-25">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-globe2 fs-4"></i>
                        </div>
                        <div>
                            <span class="text-uppercase text-muted fw-medium small">Sito Web Errato / Assente</span>
                            <h3 class="fw-bold fs-2 mb-0 mt-1 text-dark">
                                <?= number_format($totalWebsiteErrors, 0, ',', '.') ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-stat p-3 h-100 border-danger border-opacity-25">
                    <div class="d-flex align-items-center">
                        <div class="icon-shape bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-envelope-x fs-4"></i>
                        </div>
                        <div>
                            <span class="text-uppercase text-muted fw-medium small">Email / PEC Non Valida</span>
                            <h3 class="fw-bold fs-2 mb-0 mt-1 text-dark">
                                <?= number_format($totalEmailErrors, 0, ',', '.') ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card p-3 shadow-sm">
            <table id="errors-table"
                   data-toggle="table"
                   data-height="600"
                   data-pagination="true"
                   data-side-pagination="server"
                   data-search="true"
                   data-show-search-button="true"
                   data-query-params="queryParams"
                   data-classes="table table-hover table-borderless align-middle mb-0"
                   data-url="/ajax/get_errors">
                <thead class="table-light border-bottom">
                    <tr>
                        <th data-field="codice_ipa" data-width="120">Codice IPA</th>
                        <th data-field="denominazione">Denominazione Ente</th>
                        <th data-field="sito_web">Sito Web Registrato</th>
                        <th data-field="anomalies">Anomalie Rilevate</th>
                        <th data-field="actions" data-width="100" data-align="center">Azione</th>
                    </tr>
                </thead>
            </table>
        </div>

        <script>
        function queryParams(params) {
            return {
                limit: params.limit,
                offset: params.offset,
                search: params.search
            };
        }
        </script>

    </div>
</main>
<!-- /MAIN CONTENT -->

<?php
require_once __DIR__ . '/footer.inc.php';
?>
