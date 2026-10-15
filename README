# Siti Web PA — Monitoraggio e Analisi della Pubblica Amministrazione

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql)](https://www.mysql.net/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap)](https://getbootstrap.com/)

**Siti Web PA** è una piattaforma *open source* pensata per automatizzare il tracciamento, il monitoraggio dello stato di salute e la mappatura geografica degli enti della Pubblica Amministrazione Italiana presenti nell'dataset ufficiale **IndicePA** (`enti.json`).

Il sistema esegue verifiche periodiche sulla raggiungibilità dei siti web ufficiali, monitora cambi di infrastruttura (Hosting/Cloud e Mail Server), cattura screenshot automatizzati tramite Headless Chrome ed evidenzia le anomalie sintattiche o di risoluzione DNS/SSL.

## Funzionalità Principali

- ** Sincronizzazione Dataset IPA**: Integrazione automatica e download programmato del dataset ufficiale IndicePA tramite le API CKAN di `indicepa.gov.it`.
- ** Audit Trail & Storico Eventi (`Enti_History`)**: Registrazione automatica dei cambi di stato (Online/Offline/SSL), modifiche del titolo della pagina, cambi di hosting/cloud provider, variazioni dei record MX e tracciamento del ciclo di vita degli enti (Aggiunta/Rimozione da IPA).
- ** Health Check Multilivello**:
  - Verifica della risoluzione DNS (Record A / AAAA) e Mail Server (Record MX).
  - Controllo risposte HTTP/cURL e gestione proattiva degli errori sui certificati SSL/TLS.
  - Identificazione automatica del Provider Hosting/Cloud (Aruba, AWS, Azure, Google Cloud, TIM, Cineca, ecc.) tramite analisi IP.
- ** Screenshot Automatizzati**: Generazione di schermate delle homepage tramite Google Chrome Headless integrato in modalità a basso consumo di memoria (ottimizzato per container LXC/Docker).
- ** Mappatura Spaziale (Leaflet.js)**: Geolocalizzazione automatica degli enti su mappa interattiva italiana tramite integrazione del codice ISTAT e dei centroidi CAP.
- ** Tracciamento Caselle di Posta**: Categorizzazione e validazione delle caselle primarie e secondarie (`Mail1`, `Mail2`) con identificazione visiva delle caselle PEC rispetto alle caselle ordinarie.
- ** Interfaccia Web Dinamica**: Dashboard moderna Bootstrap 5 con paginazione AJAX lato server per la consultazione fluida di decine di migliaia di record.

## Architettura del Database

Il sistema si appoggia su tre tabelle principali:

1. **`Enti`**: Anagrafica principale dell'ente (Codice IPA, Denominazione, Tipologia, Codice ISTAT, CAP, Indirizzo, Coordinate geografiche, Sito Istituzionale).
2. **`Tags`**: Key-Value Store flessibile per attributi dinamici (`hosting_provider`, `website_title`, `screenshot`, `mail1`, `mail1_type`, ecc.).
3. **`Enti_History`**: Registro storico degli eventi e dei cambi di stato nel tempo.
4. **`Cap_Coordinates`**: Tabella di riferimento geografico per l'auto-completamento delle coordinate (Latitude/Longitude) per CAP e codice ISTAT.

## Requisiti di Sistema

- **Linux** (Ubuntu/Debian consigliai, nativo o in container LXC/Docker)
- **PHP 8.1+** con estensioni `pdo_mysql`, `curl`, `mbstring`, `json`, `gd`
- **MySQL 8.0+** o **MariaDB 10.5+**
- **Google Chrome** (`google-chrome-stable`) per la cattura degli screenshot
- **Subfinder** e **WhatWeb** (opzionali per scansione sottodomini e metadati)

## Installazione

### 1. Clona il Repository
```bash
git clone git@github.com:michelep/Siti-Web-PA.git

### 2. Configura l'accesso al Database
```bash
mkdir ~/config

crea il file config.inc.php ed aggiungi l'array di configurazione database:

<?php

$CFG["dbHost"] = 'localhost';
$CFG["dbDatabase"] = '';
$CFG["dbUser"] = '';
$CFG["dbPass"] = '';

?>

successivamente caricare lo schema del database in ~/setup/pa.sql e, er finire, aggiungere nel crontab l'esecuzione oraria di ~/cron/hourly.php
