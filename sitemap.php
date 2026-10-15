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

header('Content-Type: application/xml; charset=utf-8');

$host = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'pa.zerozone.it');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemapindex.org/schemas/sitemap/0.9">
    <url>
        <loc><?= $host ?>/</loc>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $host ?>/about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?= $host ?>/screenshot</loc>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
<?php
$db = Database::getInstance();
$sql = 'SELECT Codice_IPA, Data_controllo FROM Enti WHERE Data_controllo IS NOT NULL ORDER BY Data_controllo DESC';
$stmt = $db->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $codIpa = htmlspecialchars($row['Codice_IPA'], ENT_QUOTES, 'UTF-8');
    $lastMod = !empty($row['Data_controllo']) ? date('C', strtotime($row['Data_controllo'])) : date('C');
    ?>
    <url>
        <loc><?= $host ?>/details/<?= $codIpa ?></loc>
        <lastmod><?= $lastMod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php
}
?>
</urlset>
