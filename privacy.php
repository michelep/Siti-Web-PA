<?php

/**
 * Progetto: Siti Web PA
 * Informativa sulla Privacy e Trattamento dei Dati (GDPR)
 *
 * @package   SitiWebPA
 * @author    Michele Pinassi <o-zone@zerozone.it>
 * @copyright Copyright (c) 2026 Michele Pinassi
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/common.inc.php';

$gbTitle = 'Informativa sulla Privacy - Siti Web PA';
require_once __DIR__ . '/header.inc.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">

            <!-- Intestazione -->
            <div class="text-center mb-5">
                <h1 class="display-6 fw-bold">Informativa sulla Privacy</h1>
                <p class="lead text-muted">
                    Trasparenza sul trattamento dei dati personali e sulle tecnologie di misurazione del traffico adottate da questo sito.
                </p>
            </div>

            <!-- Titolare del Trattamento -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h5 fw-bold text-dark mb-3">
                    <i class="bi bi-person-vcard text-primary me-2"></i>1. Titolare del Trattamento
                </h2>
                <p class="text-secondary small mb-0">
                    Il titolare del trattamento dei dati raccolti tramite la piattaforma <strong>Siti Web PA</strong> (<em>pa.zerozone.it</em>) è
                    <strong>Michele Pinassi</strong> (Siena, Italia). Per qualsiasi chiarimento, segnalazione o esercizio dei diritti in materia di dati personali,
                    è possibile contattare il titolare all'indirizzo email:
                    <a href="mailto:o-zone@zerozone.it" class="fw-semibold text-decoration-none">o-zone@zerozone.it</a>.
                </p>
            </div>

            <!-- Tipologia di Dati Trattati e Matomo Analytics -->
            <div class="card border-0 shadow-sm p-4 mb-4 border-start border-4 border-primary">
                <h2 class="h5 fw-bold text-dark mb-3">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i>2. Statistiche di Navigazione (Matomo Self-Hosted)
                </h2>
                <p class="text-secondary small mb-3">
                    Per analizzare l'utilizzo del sito e migliorare l'esperienza di navigazione, questo portale fa uso del software open source
                    <strong>Matomo Analytics</strong> installato in <u>istanza locale (self-hosted)</u> sui nostri server.
                </p>

                <div class="bg-light p-3 rounded-3 border mb-3">
                    <h3 class="h6 fw-bold text-dark mb-2"><i class="bi bi-shield-check text-success me-2"></i>Garanzie di Riservatezza Matomo:</h3>
                    <ul class="text-secondary small mb-0 ps-3">
                        <li class="mb-1"><strong>Nessuna cessione a terzi:</strong> I dati di traffico rimangono sul nostro server in Italia e non vengono mai condivisi né venduti a fornitori esterni o piattaforme pubblicitarie (a differenza di Google Analytics).</li>
                        <li class="mb-1"><strong>Anonimizzazione dell'indirizzo IP:</strong> Gli indirizzi IP dei visitatori vengono automaticamente mascherati (vengono azzerati gli ultimi ottetti), rendendo impossibile il tracciamento diretto dell'utente o l'identificazione della singola persona.</li>
                        <li class="mb-1"><strong>Nessun tracciamento cross-site:</strong> Non vengono impiegati cookie di profilazione o traccianti utili a seguire le attività dell'utente su altri siti web.</li>
                    </ul>
                </div>

                <p class="text-secondary small mb-0">
                    Il trattamento dei log di navigazione in forma anonima si basa sul legittimo interesse del titolare ad assicurare il corretto funzionamento
                    e la sicurezza dell'infrastruttura web (Art. 6, comma 1, lett. f del Regolamento UE 2016/679 - GDPR).
                </p>
            </div>

            <!-- Dati IndicePA e Dati Pubblici -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h5 fw-bold text-dark mb-3">
                    <i class="bi bi-building text-primary me-2"></i>3. Dati della Pubblica Amministrazione
                </h2>
                <p class="text-secondary small mb-0">
                    I dati relativi alle Pubbliche Amministrazioni italiane presenti sulla piattaforma (denominazione, codice IPA, indirizzo, siti web e contatti istituzionali)
                    provengono esclusivamente dai dataset pubblici rilasciati da AgID su <strong>IndicePA</strong> sotto licenza aperta. Le caselle di posta elettronica censite
                    corrispondono unicamente a recapiti di funzione istituzionale (es. protocollo, segreteria, URP) già di pubblico dominio. Non vengono trattati o indicizzati dati personali di privati.
                </p>
            </div>

            <!-- Log di Sistema e Cookie -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h5 fw-bold text-dark mb-3">
                    <i class="bi bi-hdd-stack text-primary me-2"></i>4. Log di Server e Cookie
                </h2>
                <p class="text-secondary small mb-2">
                    <strong>Log di Sistema:</strong> Durante la normale navigazione, i web server registrano automaticamente informazioni tecniche di base (URI richiesta, orario, esito HTTP, user-agent). Tali log sono usati esclusivamente per motivi di diagnostica di rete e prevenzione di attacchi o abusi.
                </p>
                <p class="text-secondary small mb-0">
                    <strong>Cookie Tecnici:</strong> Il sito utilizza unicamente cookie tecnici strettamente necessari al funzionamento dell'applicazione web o al mantenimento delle preferenze di sessione. Non viene fatto alcun uso di cookie di profilazione.
                </p>
            </div>

            <!-- Diritti dell'Interessato -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h5 fw-bold text-dark mb-3">
                    <i class="bi bi-shield-lock text-primary me-2"></i>5. Diritti degli Interessati
                </h2>
                <p class="text-secondary small mb-0">
                    Ai sensi del Regolamento GDPR (Artt. 15-22), gli utenti hanno il diritto di richiedere l'accesso ai propri dati personali, la rettifica, la cancellazione o la limitazione del trattamento, nonché di opporsi allo stesso. Tali richieste possono essere inviate via email al Titolare del Trattamento. Qualora si ritenga che il trattamento violi le norme vigenti, è sempre possibile proporre reclamo all'Autorità Garante per la Protezione dei Dati Personali (<em>www.garanteprivacy.it</em>).
                </p>
            </div>

            <div class="text-center text-muted small mt-4">
                Ultimo aggiornamento dell'informativa: ottobre 2026
            </div>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/footer.inc.php'; ?>
