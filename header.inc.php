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

// 1. Ensure $currentUri is always a string
$currentUri = (string)($_SERVER['REQUEST_URI'] ?? '/');

// 2. Fallbacks for page titles and meta descriptions
$pageTitle = isset($gbTitle) && is_string($gbTitle) && $gbTitle !== ''
    ? htmlspecialchars($gbTitle, ENT_QUOTES, 'UTF-8') . ' | Monitoraggio Siti Web PA'
    : 'Siti Web PA | Monitoraggio e Sicurezza della Pubblica Amministrazione';

$metaDescription = isset($gbDescription) && is_string($gbDescription) && $gbDescription !== ''
    ? htmlspecialchars($gbDescription, ENT_QUOTES, 'UTF-8')
    : 'Piattaforma civica indipendente per il monitoraggio della sicurezza dei siti della Pubblica Amministrazione Italiana.';

$canonicalUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'pa.zerozone.it') . htmlspecialchars($currentUri, ENT_QUOTES, 'UTF-8');
$ogImage = isset($gbOgImage) && is_string($gbOgImage) ? $gbOgImage : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'pa.zerozone.it') . '/img/og-cover.png';

?>
<!doctype html>
<html lang="it" class="h-100">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Title & Basic Meta -->
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $metaDescription ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <meta name="author" content="Michele Pinassi">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230066cc'><path d='M5.072 11.293a.5.5 0 0 0 .708-.001L7 9.993l1.22 1.299a.5.5 0 0 0 .736.01l2.25-2.25a.5.5 0 1 0-.708-.707L8.354 10.45 7.354 9.388a.5.5 0 0 0-.73-.01L5.071 10.586a.5.5 0 0 0 .001.707z'/><path d='M8 0c-.69 0-1.382.09-2.073.273L3.18 1.025A1.5 1.5 0 0 0 2 2.47v5.528c0 3.86 2.43 7.085 6 8.002 3.57-.917 6-4.142 6-8.002V2.47a1.5 1.5 0 0 0-1.18-1.445L10.073.273A12.02 12.02 0 0 0 8 0z'/></svg>">

    <!-- Open Graph / Facebook / LinkedIn -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $metaDescription ?>">
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta property="og:locale" content="it_IT">
    <meta property="og:site_name" content="Siti Web PA">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= $metaDescription ?>">
    <meta name="twitter:image" content="<?= $ogImage ?>">

    <!-- Stylesheets -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/bootstrap-table.min.css" rel="stylesheet">
    <link href="/css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" href="/img/favicon.ico">
    <link href="/css/common.css" rel="stylesheet">

<!-- Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="https://stats.zerozone.it/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '4']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>

<!-- End Matomo Code -->
  </head>
  <body class="d-flex flex-column h-100">
    <header class="mb-4">
        <nav class="navbar navbar-expand-md navbar-dark navbar-custom navbar-slate fixed-top border-bottom border-secondary border-opacity-25 py-2">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center fw-semibold fs-5" href="/">
                    <span>Siti Web PA</span>
                </a>

                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <ul class="navbar-nav me-auto ms-3 mb-2 mb-md-0">
                        <li class="nav-item">
                            <a class="nav-link px-3 <?= str_starts_with($currentUri, '/map') ? 'active fw-semibold' : '' ?>" href="/map">
                                <i class="bi  bi-geo-alt-fill me-1"></i> Mappa
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link px-3 <?= str_starts_with($currentUri, '/errors') ? 'active fw-semibold' : '' ?>" href="/errors">
                                <i class="bi bi-exclamation-triangle me-1"></i> Anomalie
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link px-3 <?= str_starts_with($currentUri, '/screenshot') ? 'active fw-semibold' : '' ?>" href="/screenshot">
                                <i class="bi bi-grid-3x3-gap me-1"></i> Screenshots
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link px-3 <?= str_starts_with($currentUri, '/about') ? 'active fw-semibold' : '' ?>" href="/about">
                                <i class="bi bi-info-circle me-1"></i> Il progetto
                            </a>
                        </li>
                    </ul>

                    <div class="d-flex">
                        <a href="https://indicepa.gov.it/ipa-dati/dataset/enti" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm rounded-pill px-3">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Dataset IPA
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <div style="margin-top: 70px;"></div>
