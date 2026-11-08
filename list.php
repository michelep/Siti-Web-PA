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
