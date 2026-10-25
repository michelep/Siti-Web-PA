<?php

/**
 * Progetto: Siti Web PA
 * Informazioni sul progetto, finalità civiche e trasparenza metodologica
 *
 * @package   SitiWebPA
 * @author    Michele Pinassi <o-zone@zerozone.it>
 * @copyright Copyright (c) 2026 Michele Pinassi
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/common.inc.php';

$pageTitle = 'Il Progetto - Siti Web PA';
require_once __DIR__ . '/header.inc.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">

            <!-- Intestazione -->
            <div class="text-center mb-5">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-2">
                    Open Data &amp; Security Awareness
                </span>
                <h1 class="display-6 fw-bold">Siti Web PA</h1>
                <p class="lead text-muted">
                    Iniziativa civica e indipendente per l'osservazione, il monitoraggio dello stato di salute
                    e l'analisi tecnologica dei portali web della Pubblica Amministrazione Italiana.
                </p>
            </div>

            <!-- Obiettivi e Ambiti di Controllo -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h4 fw-bold text-dark mb-3">
                    <i class="bi bi-bullseye text-primary me-2"></i>Cosa Monitoriamo
                </h2>
                <p class="text-secondary mb-3">
                    Il progetto interroga periodicamente l'elenco degli enti censiti in
                    <a href="https://indicepa.gov.it/ipa-dati/dataset/enti" target="_blank" rel="noopener noreferrer">IndicePA</a>,
                    eseguendo verifiche automatizzate a livello di rete, crittografia e sicurezza applicativa:
                </p>

                <div class="row g-3 pt-2">
                    <!-- Sicurezza Web & Headers -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h3 class="h6 fw-bold text-dark mb-2">
                                <i class="bi bi-shield-lock text-success me-2"></i>Header di Sicurezza HTTP
                            </h3>
                            <p class="small text-muted mb-0">
                                Verifica passiva delle intestazioni consigliate da AgID e ACN:
                                <code>HSTS</code>, <code>Content-Security-Policy (CSP)</code>, <code>X-Frame-Options</code>,
                                <code>X-Content-Type-Options</code> e <code>Referrer-Policy</code>, con attribuzione di un grado sintetico di adozione.
                            </p>
                        </div>
                    </div>

                    <!-- Email Security -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h3 class="h6 fw-bold text-dark mb-2">
                                <i class="bi bi-envelope-check text-primary me-2"></i>Protezione Posta (SPF &amp; DMARC)
                            </h3>
                            <p class="small text-muted mb-0">
                                Ispezione dei record DNS contro il phishing e l'email spoofing:
                                configurazione dei server <code>MX</code>, validazione delle regole <code>SPF</code> e severità della policy
                                <code>DMARC</code> (<code>none</code>, <code>quarantine</code>, <code>reject</code>).
                            </p>
                        </div>
                    </div>

                    <!-- Crittografia e Salute Rete -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h3 class="h6 fw-bold text-dark mb-2">
                                <i class="bi bi-activity text-danger me-2"></i>Raggiungibilità &amp; Certificati TLS
                            </h3>
                            <p class="small text-muted mb-0">
                                Controllo dello stato di risposta HTTP/HTTPS, risoluzione dei record <code>A</code>/<code>AAAA</code>
                                e verifica della validità dei certificati crittografici SSL/TLS per prevenire disservizi.
                            </p>
                        </div>
                    </div>

                    <!-- Stack Software & Cloud -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <h3 class="h6 fw-bold text-dark mb-2">
                                <i class="bi bi-hdd-network text-info me-2"></i>Infrastruttura &amp; CMS
                            </h3>
                            <p class="small text-muted mb-0">
                                Mappatura dei Content Management System adottati (WordPress, Drupal, Joomla, ecc.),
                                versioni di PHP esposte e hosting/cloud provider di appoggio (Cineca, TIM, Aruba, AWS, Azure, ecc.).
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metodologia e Limiti di Analisi (Note Legali & Etica) -->
            <div class="card border-0 shadow-sm p-4 mb-4 border-start border-4 border-primary">
                <h2 class="h4 fw-bold text-dark mb-3">
                    <i class="bi bi-shield-shaded text-primary me-2"></i>Metodologia Non Invasiva (OSINT)
                </h2>
                <p class="text-secondary mb-3">
                    Le rilevazioni avvengono secondo criteri rigorosi di <strong>Open Source Intelligence (OSINT) passiva</strong>
                    ed etica di rete:
                </p>
                <ul class="text-secondary mb-3 small">
                    <li class="mb-2"><strong>Nessuna azione intrusiva o di penetration testing:</strong> Il sistema non inietta payload, non esegue vulnerability assessment (VA), né tenta accessi non autorizzati o forzature brute-force.</li>
                    <li class="mb-2"><strong>Sola ispezione dei dati pubblicamente esposti:</strong> Vengono interrogate esclusivamente le risorse rese pubbliche dai server web (record DNS pubblici, certificati TLS, intestazioni HTTP e markup standard delle homepage).</li>
                    <li class="mb-2"><strong>Rate Limiting conservativo:</strong> Le richieste sono cadenzate a intervalli orari per evitare qualsiasi impatto sui sistemi delle amministrazioni coinvolte.</li>
                </ul>
                <div class="alert alert-secondary d-flex align-items-center mb-0 py-2 small" role="alert">
                    <i class="bi bi-terminal me-2 text-primary fs-5"></i>
                    <div>
                        <strong>Identificazione del Bot:</strong> Le richieste HTTP inviate dal monitor sono riconoscibili tramite User-Agent:<br>
                        <code class="text-break">Mozilla/5.0 (compatible; SitiWebPA-Bot/2.0; +https://pa.zerozone.it/about; o-zone@zerozone.it)</code>
                    </div>
                </div>
            </div>

            <!-- Trattamento dei Dati (GDPR) e Disclaimer -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h4 fw-bold text-dark mb-3">
                    <i class="bi bi-file-earmark-lock text-primary me-2"></i>Trattamento Dati e Responsabilità
                </h2>
                <p class="small text-secondary mb-2">
                    <strong>Fonte dei dati anagrafici:</strong> I riferimenti istituzionali (denominazione, codice IPA, indirizzo e recapiti)
                    provengono dai dataset aperti rilasciati su <em>IndicePA</em> sotto licenza aperta.
                </p>
                <p class="small text-secondary mb-2">
                    <strong>Dati personali e contatti:</strong> Le caselle di posta e i recapiti registrati corrispondono solo a punti di contatto
                    istituzionali o di funzione (protocollo, segreteria, URP) già pubblici. Non vengono trattati dati personali privati.
                </p>
                <p class="small text-secondary mb-0">
                    <strong>Carattere sperimentale:</strong> I controlli sono elaborati in modo interamente automatizzato. Possono verificarsi
                    falsi positivi temporanei dovuti a disservizi transitori di rete o filtri perimetrali di terzi. Non si fornisce alcuna garanzia
                    di completezza o veridicità assoluta delle informazioni esposte.
                </p>
            </div>

            <!-- Stack Tecnologico e Open Source -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h4 fw-bold text-dark mb-3">
                    <i class="bi bi-code-slash text-primary me-2"></i>Trasparenza e Software Libero
                </h2>
                <p class="text-secondary small mb-3">
                    La piattaforma è sviluppata su stack <strong>LAMP</strong> (Linux, Apache, MySQL, PHP 8.1+) e impiega strumenti
                    open source consolidati quali <em>Bootstrap 5</em>, <em>Leaflet.js</em>, <em>WhatWeb</em>, <em>Subfinder</em> e <em>Google Chrome Headless</em>.
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="https://github.com/michelep/Siti-Web-PA" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-github me-1"></i> Repository GitHub
                    </a>
                    <span class="badge bg-secondary-subtle text-secondary border px-3 py-2">
                        Licenza GNU GPL v3.0
                    </span>
                    <a href="mailto:o-zone@zerozone.it" class="btn btn-outline-secondary btn-sm ms-auto">
                        <i class="bi bi-envelope me-1"></i> Contatto / Segnalazioni
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/footer.inc.php'; ?>
