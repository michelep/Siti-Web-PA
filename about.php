<?php

declare(strict_types=1);

// Set the title for the header template
$gbTitle = 'Il progetto';

require_once __DIR__ . '/common.inc.php';
require_once __DIR__ . '/header.inc.php';

?>
<!-- MAIN -->
<main class="flex-shrink-0 py-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h3 class="mb-4">Siti Web PA</h3>

                <p class="lead">
                    Il progetto si propone di essere un supporto per la Pubblica Amministrazione italiana,
                    con l'obiettivo di migliorare la consapevolezza e la sicurezza delle piattaforme web
                    istituzionali attraverso una maggiore trasparenza sulle tecnologie adottate.
                </p>

                <p>
                    La piattaforma sfrutta esclusivamente software libero, è basata sul stack tecnologico LAMP
                    ed impiega strumenti di analisi open-source come <strong>WhatWeb</strong> e <strong>Subfinder</strong>.
                </p>

                <div class="alert alert-info border-0 shadow-sm mt-4" role="alert">
                    <h5 class="alert-heading"><i class="bi bi-shield-check me-2"></i>Metodologia di analisi</h5>
                    <p class="mb-0">
                        Non vengono effettuate azioni invasive sui bersagli: le analisi si limitano esclusivamente
                        ad un'attività di <em>discovery</em> di quanto pubblicamente esposto. Non viene eseguita
                        alcuna attività di vulnerability assesment, penetration testing né tentativi di brute forcing
                        su servizi protetti da autenticazione.
                    </p>
                </div>

                <p>
                    Il codice del progetto è pubblicamente disponibile su GitHub <a href="https://github.com/michelep/Siti-Web-PA">github.com/michelep/Siti-Web-PA</a>.
                </p>
            </div>
        </div>
    </div>
</main>
<!-- /MAIN -->
<?php

require_once __DIR__ . '/footer.inc.php';

?>
