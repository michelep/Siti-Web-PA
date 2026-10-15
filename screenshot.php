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

$gbTitle = 'Screenshots Portali PA';

require_once __DIR__ . '/common.inc.php';
require_once __DIR__ . '/header.inc.php';

// 1. Pagination Configuration
$itemsPerPage = 12;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$db = Database::getInstance();

// 2. Count Total Enti with Screenshots
$countSql = "SELECT COUNT(*) FROM Enti WHERE Data_controllo IS NOT NULL";
$totalItems = (int)$db->query($countSql)->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));

// Adjust current page if out of bounds
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// 3. Fetch Paginated Records
$sql = 'SELECT Codice_IPA FROM Enti
        WHERE Data_controllo IS NOT NULL
        ORDER BY Data_controllo DESC
        LIMIT :limit OFFSET :offset';

$sth = $db->prepare($sql);
$sth->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$sth->bindValue(':offset', $offset, PDO::PARAM_INT);
$sth->execute();
?>

<!-- MAIN CONTENT -->
<main class="flex-shrink-0 my-4">
    <div class="container">

        <!-- PAGE HEADER -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
            <div>
                <h2 class="h3 fw-bold mb-1"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Galleria Screenshots</h2>
                <p class="text-muted mb-0">Anteprime grafiche e monitoraggio visivo dei portali istituzionali censiti</p>
            </div>
            <div class="mt-3 mt-md-0">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm">
                    Totale portali: <?= number_format($totalItems, 0, ',', '.') ?>
                </span>
            </div>
        </div>

        <!-- SCREENSHOTS GRID -->
        <div class="row g-4 mb-5">
        <?php
        $hasItems = false;
        while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $hasItems = true;
            $ente = new Ente();

            if ($ente->load($row['Codice_IPA'])) {
                $codiceIpa     = htmlspecialchars($ente->codiceIpa ?? '', ENT_QUOTES, 'UTF-8');
                $screenshot    = htmlspecialchars($ente->getScreenshot(), ENT_QUOTES, 'UTF-8');
                $title         = htmlspecialchars($ente->getTag('website_title') ?? 'Sito Istituzionale', ENT_QUOTES, 'UTF-8');
                $denominazione = htmlspecialchars($ente->denominazioneEnte ?? 'Ente Pubblico', ENT_QUOTES, 'UTF-8');
                ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card card-preview h-100 shadow-sm">
                        <a href="/details/<?= $codiceIpa ?>">
                            <img class="card-img-top" src="<?= $screenshot ?>" alt="<?= $title ?>" loading="lazy">
                        </a>
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div>
                                <span class="badge bg-light text-secondary border mb-2"><?= $codiceIpa ?></span>
                                <h6 class="card-title text-dark fw-semibold mb-0 text-truncate" title="<?= $denominazione ?>">
                                    <?= $denominazione ?>
                                </h6>
                            </div>
                            <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="bi bi-globe me-1"></i> IPA</small>
                                <a href="/details/<?= $codiceIpa ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Dettagli</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }

        if (!$hasItems): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-camera-video-off fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted">Nessun valore o screenshot disponibile al momento.</p>
            </div>
        <?php endif; ?>
        </div>

        <!-- PAGINATION CONTROLS -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Navigazione pagine" class="d-flex justify-content-center">
                <ul class="pagination pagination-md">

                    <!-- FIRST & PREVIOUS BUTTONS -->
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-item-link page-link" href="?page=1" aria-label="Prima pagina">
                            <span aria-hidden="true">&laquo;&laquo;</span>
                        </a>
                    </li>
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>" aria-label="Precedente">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <!-- NUMBERED PAGE BUTTONS -->
                    <?php
                    $range = 2; // Number of page buttons before/after current
                    $startPage = max(1, $currentPage - $range);
                    $endPage = min($totalPages, $currentPage + $range);

                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= ($i === $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- NEXT & LAST BUTTONS -->
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>" aria-label="Successiva">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $totalPages ?>" aria-label="Ultima pagina">
                            <span aria-hidden="true">&raquo;&raquo;</span>
                        </a>
                    </li>

                </ul>
            </nav>
        <?php endif; ?>

    </div>
</main>
<!-- /MAIN CONTENT -->

<?php
require_once __DIR__ . '/footer.inc.php';
?>
