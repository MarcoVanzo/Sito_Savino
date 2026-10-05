# Documentazione Tecnica Infrastruttura — Savino Del Bene Volley

> Ultimo aggiornamento: 4 ottobre 2026
> Versione: 3.2
>
Questo documento descrive come è costruita e come funziona l'infrastruttura che
serve il sito **savinodelbenevolley.it**, il suo pannello di gestione (CMS), lo
shop e le aste: componenti, servizi esterni, rilascio delle versioni, costi,
backup, sicurezza e avvisi. Fotografa lo stato al 1 ottobre 2026, giorno in cui
il dominio è passato dal vecchio sito WordPress a questa piattaforma
(procedura in `docs/GO_LIVE.md`). La versione 3.1 (3 ottobre) aggiunge la
rotazione delle chiavi (ActiveCampaign, token DigitalOcean, chiavi Spaces) e la
gestione delle foto caricate dal pannello (§3.5). La 3.2 (4 ottobre) aggiunge gli account
della società (§10).

<!-- La versione stampabile docs/INFRASTRUCTURE.html si rigenera da questo file con
python3 scripts/genera-infrastructure-html.py: non si modifica a mano. -->

---

## Indice

- [Sintesi per la direzione](#sintesi-per-la-direzione)
1. [Panoramica Architettura](#1-panoramica-architettura)
2. [Stack Tecnologico](#2-stack-tecnologico)
3. [Servizi DigitalOcean — Dettaglio](#3-servizi-digitalocean--dettaglio)
4. [Flusso delle Richieste](#4-flusso-delle-richieste)
5. [Variabili d'Ambiente](#5-variabili-dambiente)
6. [Build e Deploy](#6-build-e-deploy)
7. [Performance e OPcache](#7-performance-e-opcache)
8. [Costi](#8-costi)
9. [Backup e Sicurezza](#9-backup-e-sicurezza)
10. [Account della società](#10-account-della-società)

---

## Sintesi per la direzione

### Cosa c'è in produzione

Il sito pubblico, il pannello di gestione dei contenuti, lo shop e le aste
girano su **DigitalOcean**, in un unico data center a **Francoforte** (Unione
Europea). L'applicazione è divisa in tre componenti — sito, coda dei lavori in
background, pianificatore — che usano lo stesso database MySQL gestito e lo
stesso archivio dei file (Spaces). Un server a parte esegue il riconoscimento
dei volti per l'archivio fotografico ed è raggiungibile solo dalla rete
privata. Dal 1 ottobre 2026 il dominio `savinodelbenevolley.it` punta qui.

### Costi

| Voce | Importo |
|------|---------|
| Infrastruttura DigitalOcean (listino, IVA esclusa) | $71,43 al mese |
| Equivalente indicativo | ~65 € al mese, ~780 € l'anno |
| Esclusi | Commissioni Stripe e PayPal, Resend, Sentry, ActiveCampaign, eventuale traffico oltre le soglie incluse (§8) |

### Continuità e backup

| Cosa | Come | Stato al 1/10/2026 |
|------|------|--------------------|
| Database | Tre copie indipendenti: backup giornaliero di DigitalOcean (7 giorni), dump cifrato giornaliero su Spaces (90 giorni) e su Cloudflare R2, fuori da DigitalOcean | ✅ 40 dump su 40 riusciti dal 24/08 |
| Prova di ripristino | Ogni lunedì l'ultimo dump viene ripristinato davvero in un database di prova | ✅ Riuscita il 21/09 e il 28/09 |
| File e foto | Copia settimanale su Spaces e su R2 | ✅ Ultima il 27/09, 81.719 file |
| Server del riconoscimento volti | Immagine giornaliera del server (7 giorni) | ✅ Attiva |

### Sicurezza e controllo

- Connessioni cifrate (HTTPS), segreti cifrati nella configurazione, database
  accessibile solo dall'applicazione e da indirizzi autorizzati.
- Content Security Policy sul sito pubblico; strumenti di statistica e
  marketing, mappe e video caricati solo dopo il consenso del visitatore.
- Ogni rilascio passa da test automatici prima di andare in produzione; un
  test fallito blocca il rilascio.
- Errori del server e del browser tracciati con Sentry; guasti (pianificatore
  fermo, coda bloccata, negozio spento, pagamenti da rivedere) segnalati per
  email al referente tecnico.

### Limiti noti e punti aperti

| Punto | Effetto | Proposta |
|-------|---------|----------|
| Un'istanza sola per il sito e un nodo solo per il database | Nessuna ridondanza: un guasto del componente o la manutenzione del database fermano il sito per qualche minuto | Accettabile per il traffico attuale. Se servirà, un nodo di standby per il database (richiede un piano superiore: costo da valutare sul listino) |
| Immagini servite dall'origine di Francoforte, senza CDN | Adeguato per chi visita dall'Italia e dall'Europa; più lento da lontano | Attivare il CDN di Spaces se cresce il traffico estero (§3.5) |

---

## 1. Panoramica Architettura

Tutti i servizi sono nella **stessa region** (Frankfurt, Germania) per minimizzare la latenza tra i componenti.

```
                              ┌─────────────────────────────────────────────┐
                              │     DigitalOcean — Region Frankfurt         │
   👤 Utente                  │                                             │
      │                       │  ┌──────────────┐     ┌──────────────┐     │
      │ HTTPS                 │  │  🌐 App Web  │────▶│  🗄️ MySQL   │     │
      ├──────────────────────▶│  │  PHP + Apache │     │   8.4 LTS    │     │
      │                       │  │  1GB, $10/mese│◀───│  1GB, $15/mese│    │
      │                       │  └──────┬───────┘     └───▲──────▲───┘     │
      │                       │         │ upload          │      │          │
      │                       │         ▼        job queue│      │cron      │
      │ immagini (Spaces)     │  ┌──────────────┐  ┌──────┴───┐ ┌┴────────┐│
      ├──────────────────────▶│  │  📦 Spaces   │  │ ⚙️ Worker│ │⏱️ Sched.││
      │                       │  │  S3 (fra1)   │  │ 0.5GB    │ │ 0.5GB   ││
      │                       │  │  $5/mese     │  │ $5/mese  │ │ $5/mese ││
      │                       │  └──────────────┘  └────┬─────┘ └─────────┘│
      │                       │                         │ API              │
      │                       │                   ┌─────▼────────┐         │
      │                       │                   │ 🖥️ CompreFace│         │
      │                       │                   │ 4GB, $24/mese│         │
      │                       │                   └──────────────┘         │
      │                       └─────────────────────────────────────────────┘
```

Fuori da questo schema: il bucket di backup `sito-savino-backups` (Spaces, stessa
region), la copia fuori sede su **Cloudflare R2** e i job di GitHub Actions che li
alimentano (§9). CompreFace è raggiungibile solo dalla VPC `default-fra1`, in cui
l'app è agganciata con la voce `vpc:` della spec.

---

## 2. Stack Tecnologico

### Backend

| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| **PHP** | `^8.4` (`composer.json`; in locale 8.5) | Linguaggio backend |
| **Laravel** | 13.34.0 | Framework MVC |
| **Filament** | 3.3.54 | Pannello admin (CMS) |
| **Inertia.js** | 2.0 (`inertia-laravel` 2.0.24) | Bridge server↔client (SSR non attivo, vedi §6) |
| **MySQL** | 8.4 LTS | Database relazionale |
| **Apache** | (heroku buildpack) | Web server |
| **OPcache + JIT** | tracing 1255 | Compilazione PHP → codice nativo |

### Frontend

| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| **Vue.js** | 3.x | Framework UI (via Inertia) |
| **Vite** | 8.x | Bundler assets |
| **Node.js** | ≥ 20 (`engines` in `package.json`: la CI usa Node 20, il build su App Platform la versione scelta dal buildpack) | Build-time (compilazione assets client) |
| **Tailwind CSS** | 3.4 | Styling |
| **Font** | Montserrat, Playfair Display | Serviti dal sito (`public/fonts`, `@font-face` in `resources/css/app.css`), non dal CDN di Google |

### Servizi e Librerie

| Tecnologia | Ruolo |
|-----------|-------|
| **Spatie Media Library** | Gestione media con conversioni automatiche |
| **CompreFace** | Riconoscimento facciale (self-hosted) |
| **Resend** | Invio email transazionali, attivo dal 25/09/2026 (`MAIL_MAILER=resend`, mittente `noreply@savinodelbenevolley.it`, dominio verificato con DKIM sul DNS della Spa). Webhook `/api/webhooks/resend` per le email non consegnate |
| **Ziggy** | Routing Laravel → JavaScript |
| **Predis** | Client Redis (usato in sviluppo; in produzione cache, sessioni e code stanno su `database`) |
| **Spatie Translatable** | Contenuti multilingua |
| **Spatie Sitemap** | Generazione sitemap SEO |
| **Sentry** | Error tracking (web, worker e scheduler, più gli errori JavaScript via `/api/diagnostica`; attivo dal 25/09/2026 con `SENTRY_LARAVEL_DSN` valorizzato). Gli errori JavaScript vanno al progetto di `SENTRY_BROWSER_DSN` (`sito-savino-browser`), con quota propria; vuoto, ripiegano su quello del server |
| **PayPal** (REST API, `PayPalPaymentService`) | Pagamenti shop e aste, modalità `live`; `php artisan paypal:verifica` controlla credenziali e webhook. La firma del webhook si verifica sul corpo grezzo ricevuto |
| **Bonifico bancario** | Shop e, dal 30/09/2026, aste; accredito confermato a mano dal pannello |
| **Stripe** (`stripe/stripe-php` 21) | Pagamenti shop e aste, **live dal 1/10/2026** (account `acct_1ULeeFATccTjPcsE`); webhook `sito-savino-shop` su `…ondigitalocean.app/api/webhooks/stripe`. Metodi accesi dalla dashboard: si conferma solo con `payment_status = paid` |
| **ActiveCampaign** | Newsletter (iscrizioni e revoche via coda) |
| **GA4 Data API** / **Meta Graph API** | Analytics del pannello (sito, Facebook, Instagram) — vedi `docs/ANALYTICS.md` |
| **Lega Volley Femminile** | Calendario, risultati, classifica e tabellini, letti dalle pagine pubbliche (`app/Services/Lvf/`) |
| **CEV** (`www-old.cev.eu`) | Calendario, risultati e classifica della Champions League, dal 27/09/2026 (`app/Services/Cev/`) |

### Repository

| Dettaglio | Valore |
|----------|--------|
| GitHub | [MarcoVanzo/sito_savino](https://github.com/MarcoVanzo/sito_savino) (pubblico) |
| Branch produzione | `main` |
| Deploy | Gated: push a `main` → workflow CI → job `deploy` solo se i test passano (`deploy_on_push: false` nello spec) |

---

## 3. Servizi DigitalOcean — Dettaglio

### 3.1 🌐 App Web (sito + CMS) — $10/mese

**Cosa fa:** È il container principale che serve tutte le pagine web. Quando un utente visita il sito o un admin accede al CMS, questa macchina elabora la richiesta PHP e restituisce la pagina HTML.

| Spec | Valore |
|------|--------|
| Slug | `apps-s-1vcpu-1gb-fixed` |
| CPU | 1 shared vCPU |
| RAM | 1 GB |
| Bandwidth | 100 GB/mese |
| Region | `fra` (Frankfurt) |
| Istanze | 1 (fissa, no scaling) |
| Web server | `heroku-php-apache2` |
| Run command | `bash start.sh` |

**Serve due applicazioni:**

| URL | Applicazione | Framework |
|-----|-------------|-----------|
| `/` | Sito pubblico (home, news, stagione, squadra, shop...) | Inertia + Vue.js (render client-side) |
| `/admin` | CMS pannello amministrativo | Filament 3 (Livewire) |

**Processo di avvio** (`start.sh`):

```
1. php artisan migrate --force          → Aggiorna schema DB
2. php artisan db:seed --class=SiteSettingSeeder --force
   php artisan db:seed --class=CorporateGovernanceSeeder --force
                                        → Seeder idempotenti di configurazione
3. php artisan storage:link             → Crea symlink storage/
4. php artisan cache:clear              → Pulisce cache vecchia (cancella anche il
                                          battito dello scheduler, vedi health check;
                                          NON tocca lo store `persistente` degli avvisi)
   php artisan gallery:riscalda-cache   → Accoda la ricostruzione dell'archivio foto
                                          (12.000 foto): non la paga il primo visitatore
5. php artisan config:cache             → Solo se le credenziali AWS sono presenti
                                          (altrimenti config:clear, per non congelare
                                          valori S3 vuoti)
   php artisan route:cache / view:cache / event:cache
6. php artisan filament:optimize        → Cachea componenti Filament
7. heroku-php-apache2 -i php-config/opcache.ini public/
                                        → Avvia Apache con OPcache tuning
```

**Health check** (`.do/app.yaml`): App Platform interroga `/up` via HTTP
(`App\Listeners\VerifyApplicationHealth`), che verifica database e cache.
Uno scheduler fermo viene segnalato ma **non** fa fallire il check (riavviare
il web non lo risolverebbe), e solo se lo stallo dura da almeno 120 s.
`initial_delay_seconds: 120` lascia margine all'avvio (migrazioni, seeder,
cache di configurazione, rotte e viste) prima del primo controllo.

> Lo scheduler **non gira più dentro il container web**: ha un componente
> dedicato (vedi §3.3). Prima era avviato in background da `start.sh` e, se
> moriva, il container restava "healthy" mentre i task si fermavano in silenzio.

---

### 3.2 ⚙️ App Worker (code) — $5/mese

**Cosa fa:** Processa i job in background. Resta in ascolto sulla tabella `jobs` nel database e quando trova un nuovo job, lo esegue senza bloccare il browser dell'utente.

| Spec | Valore |
|------|--------|
| Slug | `apps-s-1vcpu-0.5gb` |
| CPU | 1 shared vCPU |
| RAM | 512 MB |
| Region | `fra` (Frankfurt) |
| Run command | `php artisan queue:work --queue=default,ai --sleep=3 --tries=3 --max-time=3600 --max-jobs=100 --memory=384` |

**Comportamento:**
- Processa le code `default` e `ai` (in quest'ordine di priorità)
- Controlla la tabella `jobs` ogni **3 secondi**
- Un job che fallisce ha **fino a 3 tentativi in tutto** (o quanti ne dichiara il job); attese e timeout li decide il
  singolo job (`AnalyzeGalleryImageJob`: 120 s, backoff 30/60 s; newsletter:
  backoff 10/30/90 s). Senza un valore proprio vale il timeout di serie di
  `queue:work`, **60 s**: il comando non passa `--timeout`
- Si riavvia dopo **1 ora**, **100 job** o **384 MB** per prevenire memory leak
- `retry_after` della coda database è **1860 s** (`config/queue.php`), sopra il
  job più lungo (import della gallery storica e anteprime, 1800 s). Era 180 s:
  con un solo worker innocuo, con due un job ancora in corsa sarebbe stato
  ripreso dall'altro e avrebbe girato **due volte in parallelo**. Il prezzo è
  che un job rimasto orfano (worker ucciso a metà) torna in coda dopo mezz'ora.
  Un job nuovo con un timeout più lungo fa fallire
  `tests/Unit/RetryAfterDellaCodaTest.php`

**Job processati:**

| Job | Trigger | Cosa fa |
|-----|---------|---------|
| `AnalyzeGalleryImageJob` | Upload foto nel CMS (Galleria), oppure `gallery:analyze --pending` ogni ora per le foto entrate da altre vie | Scarica l'immagine da S3, la invia a CompreFace per riconoscimento facciale, salva i tag nel DB (coda `ai`) |
| `RicostruisciLaCacheDellaGallery` | Modifica di foto/album/atlete, avvio del web, ogni ora | Rigenera la cache dell'archivio foto invece di cancellarla (job unico) |
| `PerformConversionsJob` (Spatie) | Upload di un media | Conversioni immagine; richiede GD (§3.5) |
| `SyncNewsletterToActiveCampaign` | Iscrizione newsletter | Sincronizza il contatto su ActiveCampaign (coda `default`) |
| `UnsubscribeNewsletterFromActiveCampaign` | Disiscrizione o cancellazione di un iscritto | Porta la revoca su ActiveCampaign: status 2 sulla lista, oppure cancellazione del contatto se la richiesta è di cancellazione dati (coda `default`) |
| Mail transazionali | Ordini, aste, rimborsi, avviso di nuovo ordine alla società | `Mail::to(...)->queue(...)`, spedite via Resend (vedi §5). La ricevuta del recesso parte invece in modo sincrono |

**Flusso:**

```
Admin carica foto → Web accoda job nel DB → Worker lo preleva → Chiama CompreFace → Salva risultati
```

---

### 3.3 ⏱️ App Scheduler (pianificatore) — $5/mese

**Cosa fa:** Esegue i task pianificati definiti in `routes/console.php` (vedi §9).
È un componente dedicato di App Platform, separato dal web.

| Spec | Valore |
|------|--------|
| Slug | `apps-s-1vcpu-0.5gb` |
| CPU | 1 shared vCPU |
| RAM | 512 MB |
| Region | `fra` (Frankfurt) |
| Run command | `php artisan scheduler:beat && php artisan schedule:work --no-interaction` |

**Comportamento:**
- Il primo `scheduler:beat` esplicito scrive subito il battito nella cache
  condivisa: senza, dopo ogni riavvio di questo componente `/up` vedrebbe il
  battito assente e comincerebbe a contare lo stallo.
- Il battito (`scheduler:beat`, ogni minuto) è letto dall'health check `/up`
  del web: se lo scheduler muore, il problema viene segnalato invece di
  restare silenzioso.
- `instance_count` **deve restare 1**: due istanze eseguirebbero gli stessi
  comandi in parallelo. I `withoutOverlapping()` nei task sono una rete di
  sicurezza (lock condiviso via cache), non un permesso a scalare.

---

### 3.4 🗄️ Database MySQL 8.4 — $15.23/mese

**Cosa fa:** Contiene tutti i dati strutturati dell'applicazione. Ogni pagina web legge da qui, ogni azione nel CMS scrive qui.

| Spec | Valore |
|------|--------|
| Engine | MySQL 8.4 LTS |
| Size | `db-s-1vcpu-1gb` (1 vCPU, 1 GB RAM) |
| Nodi | 1 (singolo, no replica) |
| Region | `fra1` (Frankfurt) |
| Tipo | Managed (DigitalOcean gestisce backup, aggiornamenti, monitoring) |
| Connessione | Endpoint pubblico TLS (porta 25060) filtrato dalle **Trusted Sources**: l'app `sito-savino` e gli IP di sviluppo autorizzati. Il backup aggiunge l'IP del runner GitHub per la durata del dump e lo rimuove alla fine, anche su errore |
| Nome cluster | `sito-savino-db` |

**Dati contenuti (principali):**

| Categoria | Tabelle | Esempi |
|-----------|---------|--------|
| Contenuti | posts, pages, categories, hero_slides, site_settings, eventi | News (941 storiche + quelle riallineate da `wp-json` fino al 1/10), pagine CMS, impostazioni, eventi della homepage |
| Squadra | players, staff_members, rosters, player_stats, game_player_stats | Rose, statistiche stagionali ricostruite dai tabellini |
| Partite | games, seasons, teams, team_lvf_club_ids | Calendario, risultati, classifiche (sync Lega e CEV) |
| Galleria | gallery_events, gallery_images, gallery_image_person | Eventi foto, tag delle atlete (manuali e AI) |
| Shop | products, product_categories, product_category_product, storico_prezzi, orders, coupons, shipping_zones, stock_movements | Prodotti (anche in più categorie), ordini, codici sconto, fasce di peso, storico prezzi (Omnibus) |
| Consumatori | versioni_condizioni, richieste_di_recesso | Testo delle condizioni accettate da ogni ordine, dichiarazioni di recesso (non si cancellano dal pannello) |
| Aste | auctions, bids | Aste benefiche e offerte |
| Sponsor | sponsors | Loghi e link partner |
| Analytics | web_analytics_daily, social_insights_daily, social_accounts | Serie giornaliere GA4 e Meta (`docs/ANALYTICS.md`) |
| Privacy | consensi_cookie, contact_messages, newsletter_subscribers | Prove di consenso (24 mesi), messaggi e accrediti (24 mesi), iscritti alla newsletter |
| Sistema | users, activity_logs, jobs, job_batches, cache, cache_persistente, sessions, menu_items | Utenti, log, code, navigazione |
| Media | media (Spatie) | Metadati file caricati |

**Backup:** giornaliero gestito da DigitalOcean, più il dump cifrato su Spaces e R2 (§9).

---

### 3.5 📦 Spaces S3 — $5/mese

**Cosa fa:** Storage dei file (immagini, media, documenti). È un servizio di object storage compatibile con Amazon S3, quindi Laravel lo usa come se fosse AWS S3.

| Spec | Valore |
|------|--------|
| Bucket | `sito-savino-assets-2026` |
| Region | `fra1` (Frankfurt) |
| Storage incluso | 250 GB |
| Transfer incluso | 1 TB/mese |
| URL usato dal sito | `sito-savino-assets-2026.fra1.digitaloceanspaces.com` (`AWS_URL`: origine, **non** CDN) |
| URL CDN | `sito-savino-assets-2026.fra1.cdn.digitaloceanspaces.com` — non referenziato da nessuna parte nel codice né nello spec |
| Protocollo | S3 API v4 (compatibile AWS SDK) |

**File contenuti:**

| Tipo | Esempi |
|------|--------|
| Foto giocatrici | Ritratti ufficiali, foto azione |
| Hero slides | Immagini banner homepage |
| News | Immagini articoli |
| Galleria | Foto eventi, partite |
| Shop | Immagini prodotti |
| Sponsor | Loghi partner |
| Media Library | Conversioni Spatie: `thumb` (400 px) e `card` (600 px) su tutti i modelli, più `lightbox`, `detail`, `hero`, `zoom`, `og-image` secondo il modello |

**Come arriva un'immagine al browser:**
1. L'admin carica un'immagine nel CMS
2. Laravel la salva su Spaces via API S3. Fino al Salva il file sta in
   `livewire-tmp/` sul disco del container web: un rilascio (o la pulizia
   dopo 24 ore) lo cancella, e la redazione riceve l'avviso di ricaricarlo
   (`UploadTemporaneoPerso`) invece di un errore 500
3. Spatie Media Library genera le conversioni (`thumb`, `card`…), quasi tutte in coda; `zoom` e `og-image` dei prodotti subito, sul web
4. Il sito pubblico la chiede **direttamente all'origine di Frankfurt**: gli
   indirizzi nascono da `AWS_URL`, che punta all'endpoint non-CDN. Il
   `Cache-Control` che `media:fix-remote-metadata` scrive sui file vale per la
   cache del browser, non per un edge.

Passare al CDN significa cambiare `AWS_URL` sullo spec (web, worker e
scheduler) e verificare che l'endpoint CDN sia attivo sul bucket; gli indirizzi
già salvati in chiaro nei contenuti (HTML delle notizie) resterebbero
sull'origine.

**CORS del bucket: serve al pannello, non al sito.** Il sito pubblico mostra
le immagini con `<img>`, che non chiede permessi. Il pannello no: FilePond,
nei campi di upload di Filament e Media Library, **scarica con `fetch()`** i
file già caricati per mostrarne l'anteprima, e una richiesta cross-origin
verso Spaces passa solo se il bucket la ammette. La CSP del pannello
(`connect-src` con l'host di Spaces) è la metà nostra; l'altra metà è
la regola CORS del bucket, che **non sta nel repository né nella spec** e si
cambia dal pannello DigitalOcean (Spaces → `sito-savino-assets-2026` →
Settings → CORS). Senza, le foto dei prodotti restano in "Caricamento" e non
si possono modificare, senza nessun errore lato server.

Stato letto il 1/10/2026 con richieste di preflight (`OPTIONS` con
`Origin`), in sola lettura:

| Origine | Esito |
| --- | --- |
| `https://savinodelbenevolley.it` | ammessa (200) |
| `https://seashell-app-47mmf.ondigitalocean.app` | ammessa (GET, HEAD), `max-age` 86400 (lettura del 26/09) |
| `http://localhost:8000` | ammessa (GET) (lettura del 26/09) |
| `https://www.savinodelbenevolley.it` | rifiutata (403): innocuo, `www.` manda con 301 al dominio e il pannello non vi si apre mai |

Per verificare:

```
curl -s -D - -o /dev/null -X OPTIONS \
  -H "Origin: https://savinodelbenevolley.it" \
  -H "Access-Control-Request-Method: GET" \
  https://sito-savino-assets-2026.fra1.digitaloceanspaces.com/
```

deve rispondere 200 con `access-control-allow-origin` uguale all'origine.

**Foto caricate dal pannello (dal 02/10/2026).** Ogni campo di upload passa
da `App\Support\FotoAlleggerita` prima di salvare:

| Formato | Trattamento |
| --- | --- |
| JPEG | Lato lungo ridotto a 2560 px (slide della home 3840), qualità 86, profilo colore ricopiato, orientamento EXIF applicato; se il risultato non pesa meno resta l'originale |
| PNG | Nessun ridimensionamento (il GD di Linux perderebbe la trasparenza); si toglie solo il profilo colore incorporato (`iCCP`), a pixel identici: un profilo difettoso mandava in errore 500 le conversioni della scheda prodotto |
| WebP | Invariato |
| Galleria | Originale intatto, per il riconoscimento dei volti; a CompreFace va una copia ridotta sotto i 5 MB (§3.6) |

Il limite per file è 50 MB (`media-library.max_file_size`, prima 10 MB): oltre,
il campo avvisa prima dell'invio. I PNG salvati prima della correzione si
riparano, con le loro conversioni, da `php artisan foto:ripara-png`. Le
estensioni PHP `ext-gd` ed `ext-exif` sono dichiarate in `composer.json`.

> ⚠️ Le conversioni richiedono **GD**, che il buildpack `heroku/php` non abilita
> per impostazione predefinita: va richiesto con `"ext-gd": "*"` fra i `require`
> di `composer.json` (già presente). Senza, il build passa e il sito funziona,
> ma ogni `PerformConversionsJob` fallisce in coda con
> `Call to undefined function ...\Gd\imagecreatefrom*` e le immagini restano
> senza thumbnail. Se si toglie quella riga il guasto ricompare, silenzioso
> fino al primo upload.

---

### 3.6 🖥️ Droplet CompreFace — $24/mese

**Cosa fa:** Server dedicato che esegue [CompreFace](https://github.com/exadel-inc/CompreFace), un sistema open-source di riconoscimento facciale basato su deep learning.

| Spec | Valore |
|------|--------|
| Nome | `compreface-server` |
| OS | Ubuntu 24.04 LTS |
| CPU | 2 vCPU |
| RAM | 4 GB |
| Disco | 80 GB SSD |
| Region | `fra1` (Frankfurt) |
| IP privato (VPC) | 10.114.0.3 |
| Porta API | 8000, aperta alla sola VPC `10.114.0.0/20` (cloud firewall `compreface-fw`) |
| ID Droplet | 580932690 |

Chiave API vera nel Postgres interno di CompreFace, copia cifrata in
`COMPREFACE_KEY`. CompreFace rifiuta i file oltre 5 MB: il worker gli manda una
copia ridotta (`services.compreface.max_file_bytes`), l'originale resta su Spaces. Le regole sugli esempi di addestramento (volto minimo 90 px,
soglie di tag e di "da rivedere") stanno in `CLAUDE.md` §12-ter.

**Funzionalità:**

| Feature | Descrizione |
|---------|-------------|
| **Face Detection** | Rileva tutti i volti presenti in una foto |
| **Face Recognition** | Confronta un volto con i volti noti (giocatrici registrate) |
| **Face Collection** | Database dei volti noti, addestrato con le foto ufficiali |

**Flusso di utilizzo:**

```
1. Admin carica foto galleria nel CMS
2. Web container accoda AnalyzeGalleryImageJob
3. Worker preleva il job dalla coda
4. Worker scarica l'immagine da Spaces S3
5. Worker invia l'immagine a CompreFace (HTTP POST :8000/api/v1/recognition/recognize)
6. CompreFace analizza i volti e restituisce:
   - Coordinate dei volti (bounding box)
   - Identità matchate (nome giocatrice, % confidenza)
7. Worker salva i tag in gallery_image_person (con confidence_score) e segna
   ai_analyzed_at su gallery_images
```

**Perché 4 GB di RAM:** CompreFace gira in più container: API e amministrazione in Java (Spring Boot), il servizio `compreface-core` in Python che tiene in memoria i modelli di rilevamento e riconoscimento, più Postgres e il frontend. Insieme richiedono circa 2-3 GB di RAM operativa.

---

## 4. Flusso delle Richieste

### 4.1 Visita sito pubblico (es. `/news`)

Prima di tutto `PortaSullIndirizzoDelSito` manda con 301 le GET e HEAD
arrivate su `www.` o su `*.ondigitalocean.app` all'host di `APP_URL`
(`savinodelbenevolley.it`). POST, `api/*` (webhook di Stripe, PayPal e Resend)
e `/up` restano dove sono.

```
Utente → HTTPS → App Web → CachePublicResponse
                              │
          ┌───────────────────┴────────────────────┐
          │ GET anonimo, senza cookie di sessione, │  tutto il resto
          │ non Inertia, non crawler social        │  (navigazione Inertia,
          │                                        │   visitatore con sessione,
     HIT (TTL 60 s)          MISS                  │   admin, POST, crawler)
          │            query MySQL + render        │
          │            salva in cache              │  query MySQL + render
          ▼                    ▼                   ▼
                        HTML / JSON Inertia
                              │
              Immagini → Spaces (origine fra1) → Browser
```

**La cache full-page copre poco del traffico umano.** Il middleware gira prima
di `StartSession` e, per non servire a un anonimo la pagina di un utente
loggato, salta ogni richiesta che porta il cookie di sessione — che Laravel
imposta alla prima risposta. Salta anche le richieste `X-Inertia`, cioè ogni
clic interno. In pratica la cache serve la **prima pagina** di un visitatore
senza cookie e i bot; tutto il resto passa dalle cache applicative (archivio
gallery, sponsor, feed, menu). I TTFB del §7 misurati con `curl` senza cookie
sono quindi il caso migliore, non quello tipico.

### 4.2 Azione CMS (es. carica foto galleria)

```
1. Admin carica foto          → App Web riceve upload
2. App Web                    → Salva immagine su Spaces S3
3. App Web                    → INSERT in gallery_images (MySQL)
4. App Web                    → INSERT in jobs (MySQL) — accoda job
5. App Web                    → Risponde "Foto caricata"
6. (in background) Worker     → Preleva job dalla coda
7. Worker                     → Scarica immagine da S3
8. Worker                     → POST a CompreFace (:8000)
9. CompreFace                 → Analizza volti, restituisce risultati
10. Worker                    → Tag in gallery_image_person, ai_analyzed_at su gallery_images
11. Worker                    → DELETE job (completato)
```

---

## 5. Variabili d'Ambiente

### Web Container

| Variabile | Valore | Descrizione |
|----------|--------|-------------|
| `APP_ENV` | `production` | Ambiente di esecuzione |
| `APP_DEBUG` | `false` | Debug disattivato |
| `APP_KEY` | 🔒 secret | Chiave di cifratura Laravel |
| `APP_URL` | `https://${APP_DOMAIN}` | URL base: segue il dominio PRIMARY della spec (`savinodelbenevolley.it` dal 1/10/2026; `www.` è ALIAS) |
| `TRUSTED_HOSTS` | `savinodelbenevolley.it,seashell-app-47mmf.ondigitalocean.app` | L'host di `APP_URL` non si aggiunge da solo; su `ondigitalocean.app` restano webhook (Resend, Stripe, PayPal) e sorveglianza |
| `APP_LOCALE` | `it` | Lingua predefinita |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `${sito-savino-db.HOSTNAME}` | Host DB (injected da DO) |
| `DB_PORT` | `${sito-savino-db.PORT}` | Porta DB (injected da DO) |
| `DB_DATABASE` | `${sito-savino-db.DATABASE}` | Nome database |
| `DB_USERNAME` | `${sito-savino-db.USERNAME}` | Utente DB |
| `DB_PASSWORD` | 🔒 `${sito-savino-db.PASSWORD}` | Password DB |
| `CACHE_STORE` | `database` | Driver cache (tabella MySQL `cache`) |
| `SESSION_DRIVER` | `database` | Sessioni salvate in MySQL |
| `SESSION_LIFETIME` | `480` | Otto ore: con i 120 minuti di default un salvataggio lungo nel pannello tornava 419 |
| `SESSION_ENCRYPT` | (attivo) | Sessioni cifrate |
| `SESSION_SECURE_COOKIE` | (attivo) | Cookie solo HTTPS |
| `QUEUE_CONNECTION` | `database` | Code via tabella MySQL `jobs` |
| `FILESYSTEM_DISK` | `s3` | Storage file su Spaces |
| `MEDIA_DISK` | `s3` | Media Library su Spaces |
| `FILAMENT_FILESYSTEM_DISK` | `s3` | Upload del pannello su Spaces: Filament non segue `FILESYSTEM_DISK` e di serie scriverebbe sul container effimero |
| `AWS_ACCESS_KEY_ID` | 🔒 secret | Credenziali Spaces |
| `AWS_SECRET_ACCESS_KEY` | 🔒 secret | Credenziali Spaces |
| `AWS_DEFAULT_REGION` | `fra1` | Region Spaces |
| `AWS_BUCKET` | `sito-savino-assets-2026` | Nome bucket |
| `AWS_ENDPOINT` | `https://fra1.digitaloceanspaces.com` | Endpoint S3 API |
| `AWS_URL` | `https://sito-savino-assets-2026.fra1.digitaloceanspaces.com` | URL pubblico |
| `INERTIA_SSR_ENABLED` | `false` | SSR **disattivato** (non esiste `resources/js/ssr.js` né bundle SSR) |
| `LOG_CHANNEL` | `stderr` | Log su stderr (visibili in DO dashboard) |
| `LOG_LEVEL` | `warning` | Errori e avvisi (dal 03/10/2026, prima solo errori): i log DO sono effimeri, servono da contesto per Sentry |
| `SENTRY_LARAVEL_DSN` | DSN del progetto server (regione UE) | Error tracking, attivo dal 25/09/2026 |
| `SENTRY_BROWSER_DSN` | DSN di `sito-savino-browser` | Errori JavaScript, quota separata; vuoto ripiega sul DSN del server |
| `SENTRY_SEND_DEFAULT_PII` | `false` | Nessun dato personale a Sentry: cambiarlo vuol dire cambiare prima l'informativa |
| `SENTRY_ENVIRONMENT` | `production` | Ambiente riportato a Sentry |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.1` | Campionamento performance tracing |
| `PREVIEW_AUTH_ENABLED` | `false` | Basic auth di pre-lancio **spenta**: il sito è pubblico |
| `PREVIEW_AUTH_USER` / `PREVIEW_AUTH_PASS` | 🔒 secret | Credenziali pronte per richiuderlo (con la protezione accesa e i segreti vuoti il sito risponde 503) |
| `COMPREFACE_HOST` | `http://10.114.0.3:8000` | URL server CompreFace (rete privata VPC) |
| `COMPREFACE_KEY` | 🔒 secret cifrato | API key CompreFace |
| `COMPREFACE_DETECTION_KEY` | 🔒 secret cifrato | API key del servizio di **rilevamento** CompreFace: trova i volti di sfondo da coprire prima del riconoscimento. Senza, il riconoscimento non parte |
| `ACTIVECAMPAIGN_URL` / `ACTIVECAMPAIGN_LIST_ID` | in chiaro | Endpoint e lista newsletter |
| `ACTIVECAMPAIGN_API_KEY` | 🔒 secret cifrato | Ruotata il 02/10/2026 (la vecchia era rimasta in chiaro nella cronologia del repository): il nuovo cifrato è ricopiato nello spec su web, worker e scheduler, o il deploy successivo rimetterebbe la chiave revocata |
| `GA4_SERVICE_ACCOUNT_JSON` | 🔒 secret cifrato | Service account Google (base64) per la GA4 Data API |
| `META_APP_ID` / `META_CONFIG_ID` | in chiaro | App Meta e configurazione Login for Business (non segreti) |
| `PAYPAL_CLIENT_ID` / `PAYPAL_MODE` | in chiaro / `live` | Credenziale pubblica e ambiente |
| `PAYPAL_CLIENT_SECRET` | 🔒 secret cifrato | |
| `PAYPAL_WEBHOOK_ID` | in chiaro | Entra nella verifica della firma: al cambio di dominio si **modifica l'URL** del webhook esistente, non se ne crea uno nuovo |

### Variabili a livello d'app

Dichiarate sopra i componenti nella spec: le ereditano web, worker e scheduler.

| Variabile | Valore | Descrizione |
|----------|--------|-------------|
| `META_APP_SECRET` | 🔒 secret cifrato | Segreto dell'app Meta (OAuth sul web, sync notturno sugli altri) |
| `MAIL_MAILER` | `resend` | Dal 25/09/2026; prima `config/mail.php` cadeva su `log` e nessuna email usciva |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | `noreply@savinodelbenevolley.it` / `Savino Del Bene Volley` | Il dominio deve restare verificato su Resend: il DNS della Spa pubblica `DMARC p=reject` (`docs/GO_LIVE.md` §1) |
| `RESEND_API_KEY` | 🔒 secret cifrato | Solo invio, limitata al dominio |
| `RESEND_WEBHOOK_SECRET` | 🔒 secret cifrato | Firma Svix delle notifiche di Resend |
| `AVVISI_EMAIL` | `allarmi@mv-consulting.it` | Destinatari di `AvvisoTecnico` (§9, Avvisi) |
| `STRIPE_KEY` | `pk_live_…` in chiaro | Chiave pubblicabile, live dal 1/10/2026 |
| `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | 🔒 secret cifrati | Si rigenerano dal pannello e si ricopiano da `doctl apps spec get` |

> Il deploy applica lo spec del repository: un valore aggiunto solo dal pannello
> DO viene cancellato al rilascio successivo. I segreti si scrivono dal pannello
> con "Encrypt" e si ricopiano nello spec nella forma `EV[1:…]`.

### Worker e Scheduler

Web, worker e scheduler sono **tre ambienti distinti**: una variabile scritta solo
sotto `services:` non arriva alla coda né allo scheduler. Worker e scheduler
ripetono quindi le variabili del web (`APP_KEY`, `APP_URL`, `AWS_*`,
`FILAMENT_FILESYSTEM_DISK`, `TRUSTED_HOSTS`, `COMPREFACE_*`, `ACTIVECAMPAIGN_*`,
`GA4_*`, `META_*`, `PAYPAL_*`, Sentry). Le variabili a livello d'app (sopra) non
vanno ripetute.

Due casi reali: senza `APP_URL` le email in coda uscivano con host
`http://localhost`; con un `APP_KEY` che si decifrava in stringa vuota (trovato
il 23/09/2026) `social:sync-meta` falliva ogni notte senza lasciare traccia. Ora
l'allineamento lo verifica `tests/Unit/VariabiliAllineateFraIComponentiTest.php`,
che elenca una per una, col motivo, le sole eccezioni (variabili che vivono dentro
una richiesta HTTP). Controllo manuale su ciascun componente:
`doctl apps console <app> <componente>` → `php -r 'echo strlen(getenv("APP_KEY"));'`.

---

## 6. Build e Deploy

### Pipeline di deploy

```
git push main → GitHub Actions (CI: ESLint, Vitest, Pint, PHPStan, mappa dipendenze,
                 PHPUnit, audit delle dipendenze) → job "deploy"
              → digitalocean/app_action con lo spec .do/app.yaml → Build Web + Worker + Scheduler → ACTIVE
```

Lo spec `.do/app.yaml` del repository **sovrascrive** la configurazione dell'app
ad ogni deploy: ogni variabile d'ambiente deve stare lì. Un push che tocca solo
`*.md`, `docs/**` o `Loghi/**` non avvia né CI né deploy.

### Build Web

```bash
composer install --optimize-autoloader --no-dev && npm ci --include=dev && npm run build
```

1. **Composer**: installa dipendenze PHP (autoloader ottimizzato, no dev-dependencies)
2. **NPM ci**: installa dipendenze frontend (Vue, Vite, Tailwind...)
3. **NPM build**: compila gli assets client (`vite build`). Nessun bundle SSR:
   lo script `build:ssr` esiste in `package.json` ma non è usato e manca
   l'entrypoint `resources/js/ssr.js`.

### Build Worker e Scheduler

```bash
composer install --optimize-autoloader --no-dev
```

Solo dipendenze PHP (nessuno dei due serve frontend).

### Avvio Web (`start.sh`)

| Step | Comando | Scopo |
|------|---------|-------|
| 1/6 | `migrate --force` | Applica nuove migrazioni DB (senza `\|\| true`: uno schema disallineato deve fermare l'avvio) |
| 2/6 | `db:seed SiteSettingSeeder` + `db:seed CorporateGovernanceSeeder` | Seeder idempotenti: garantiscono le impostazioni di base (un fallimento ferma l'avvio) |
| 3/6 | `storage:link` | Symlink `public/storage → storage/app/public` |
| 4/6 | `cache:clear` + `gallery:riscalda-cache` | Svuota cache del deploy precedente (cancella anche il battito dello scheduler) e accoda la ricostruzione dell'archivio foto; un fallimento di quest'ultima non ferma l'avvio |
| 5/6 | `config:cache` (solo con credenziali AWS) + `route:cache` + `view:cache` + `event:cache` | Pre-compila config, route, Blade templates, event map |
| 6/6 | `filament:optimize` | Cachea componenti, icone Filament |

### Avvio Worker

```bash
php artisan queue:work --queue=default,ai --sleep=3 --tries=3 --max-time=3600 --max-jobs=100 --memory=384
```

Polling sulla tabella `jobs` ogni 3 secondi, code `default` e `ai`, max 3 tentativi
per job, riavvio dopo 100 job / 1 ora / 384 MB.

### Avvio Scheduler

```bash
php artisan scheduler:beat && php artisan schedule:work --no-interaction
```

Un battito immediato (per l'health check del web), poi il ciclo del pianificatore.
`schedule:work` manda l'output dei comandi in `/dev/null`: un comando che esce
con errore non lascia traccia nei log di App Platform, e senza un'eccezione
nemmeno in Sentry. Per questo in fondo a `routes/console.php` ogni evento
pianificato passa da `AvvisoDelPianificatore::aggancia()`: l'output si cattura
in `storage/logs/schedule-<hash>.log` (riscritto a ogni giro) e un'uscita con
errore manda la coda dell'output per email (AvvisoTecnico, una ogni sei ore per
comando). Il ciclo deve restare l'ultima cosa del file: un evento registrato
dopo resterebbe muto, e `AvvisoDelPianificatoreTest` lo segnala.
Le eccezioni arrivano comunque a Sentry (attivo dal 25/09/2026).

---

## 7. Performance e OPcache

### Configurazione OPcache (`php-config/opcache.ini`)

| Setting | Valore | Effetto |
|---------|--------|--------|
| `opcache.jit` | `1255` | JIT tracing mode — compila PHP in codice macchina nativo |
| `opcache.jit_buffer_size` | `32M` | Buffer per il codice JIT compilato |
| `opcache.memory_consumption` | `128` MB | Memoria per il bytecode cachato |
| `opcache.max_accelerated_files` | `10000` | Max file PHP cachati (progetto usa ~1600) |
| `opcache.interned_strings_buffer` | `16` MB | Buffer stringhe internate PHP |
| `opcache.validate_timestamps` | `0` | Non verifica se i file sono cambiati (cambiano solo al deploy) |
| `opcache.revalidate_freq` | `0` | Frequenza check timestamps (irrilevante con validate=0) |
| `opcache.enable_file_override` | `1` | Ottimizza file_exists/is_file via OPcache |
| `upload_max_filesize` / `post_max_size` | `64M` / `128M` | Limiti di caricamento dal pannello (allineati a `public/.user.ini`); il limite per file dei media è più basso, 50 MB (§3.5) |

### Utilizzo in produzione (misurato il 2 luglio 2026)

> La misura va presa dal processo web, non dalla console (un PHP diverso, con
> una cache sua): margine ampio a luglio, non ripetuta dopo il go-live.

| Risorsa | Allocata | Usata | % |
|---------|----------|-------|---|
| OPcache memory | 128 MB | 69 MB | 54% |
| Interned strings | 16 MB | 10 MB | 60% |
| JIT buffer | 32 MB | <1 MB | 3% |
| File cachati | 16.229 max | 1.640 | 10% |
| Hit rate | — | 90.3% | — |

### Tempo di risposta (TTFB)

> Misure con `curl` senza cookie: per il sito pubblico sono probabilmente cache
> HIT di `CachePublicResponse` (§4.1), non il tempo di una navigazione reale.
> Il 1/10/2026 la misura è sul dominio, dietro l'edge Cloudflare di App
> Platform, da una linea diversa: le due colonne non sono confrontabili alla
> lettera.

| Pagina | TTFB 2/07 (ondigitalocean.app) | TTFB 1/10 (dominio, mediana di 3) |
|--------|------|------|
| CMS Admin `/admin/login` | **0.18s** | **0.32s** |
| Sito Home `/` | **0.15s** | **0.31s** |
| Sito Stagione `/stagione` | **0.17s** | **0.28s** |
| Sito News `/news` | **0.11s** | **0.29s** |
| Shop `/shop` | — | **0.27s** |
| TCP connect | 0.017s | 0.042s |
| TLS handshake | 0.037s | 0.118s |

### Caching layers

| Layer | Tipo | Scope |
|-------|------|-------|
| OPcache + JIT | Bytecode → machine code | Tutte le pagine PHP |
| Laravel config/route/view cache | File serializzati | Boot framework |
| Filament optimize | Componenti cachati | CMS admin |
| `CachePublicResponse` middleware | Full-page HTML cache, 60 s | Solo GET anonimi senza cookie di sessione e non Inertia (§4.1) |
| Cache applicative (store `database`) | Home, news, rose, shop, archivio gallery (1 giorno, rigenerato in coda), sponsor, feed RSS (30 min), menu | Invalidate al salvataggio (`CacheInvalidationObserver`; il menu da `MenuItem`) |
| `Cache-Control` sui file Spaces | Cache del browser | Immagini/media (nessun CDN, §3.5) |

---

## 8. Costi

### Riepilogo mensile

| # | Risorsa | Spec | Costo/mese |
|---|---------|------|------------|
| 1 | 🖥️ Droplet CompreFace | 2 vCPU, 4 GB, fra1 | **$24.00** |
| 2 | 🗄️ Database MySQL | `db-s-1vcpu-1gb`, fra1 | **$15.23** |
| 3 | 🌐 App Web | `apps-s-1vcpu-1gb-fixed`, fra | **$10.00** |
| 4 | ⚙️ Worker | `apps-s-1vcpu-0.5gb`, fra | **$5.00** |
| 5 | ⏱️ Scheduler | `apps-s-1vcpu-0.5gb`, fra | **$5.00** |
| 6 | 📦 Spaces | abbonamento: 250 GB + 1 TB di traffico, **per tutti i bucket** dell'account | **$5.00** |
| 7 | 💾 Backup droplet CompreFace | giornalieri (7 giorni), 30% del droplet | **$7.20** |
| | | **TOTALE** | **$71.43/mese** |
| | | | **~€65/mese** |
| | | | **~€780/anno** |

Cifre di listino DigitalOcean in dollari, IVA esclusa; il cambio in euro è
indicativo. Il totale va confrontato con la fattura mensile dell'account.

Voci variabili, fuori dal totale:
- **Spaces oltre i 250 GB** ($0.02/GB): il bucket di backup
  `sito-savino-backups` sta nello stesso abbonamento del bucket degli asset, e i
  media vi sono copiati per intero. Con dodicimila foto più le conversioni, la
  somma dei due bucket va controllata dal pannello prima di darla per inclusa.
- **Cloudflare R2**: 10 GB gratuiti, poi a consumo; contiene dump e media.
- **GitHub Actions**: gratuito, il repository è pubblico.
- **Servizi a consumo o con piano proprio**, fatturati fuori da DigitalOcean:
  Resend (email), Sentry, Stripe e PayPal (commissioni sulle transazioni),
  ActiveCampaign.

Nessun costo per la gestione del consenso ai cookie: Cookiebot è stato scartato
(~30 €/mese per 1958 pagine) e consenso, scansione e dichiarazione sono fatti in
casa.

---

## 9. Backup e Sicurezza

### Backup

| Componente | Backup | Frequenza | Gestione |
|-----------|--------|-----------|----------|
| Database MySQL | ✅ Managed | Giornaliero, ultimi 7 giorni (verificato 1/10: uno al giorno alle 10:05 UTC, ~0,6 GB) | DigitalOcean |
| Database MySQL | ✅ Dump GPG | Giornaliero 03:00 UTC (`backup-db.yml`; dal 24/08 al 1/10: 40 run su 40 riuscite) | Bucket `sito-savino-backups` (90 giorni) + copia su Cloudflare R2 |
| Media Spaces | ✅ Copia | Settimanale, domenica 04:00 UTC (`backup-media.yml`; ultima il 27/09, riuscita) | Bucket `sito-savino-backups`, copia cumulativa (i file non scadono; le versioni sovrascritte dopo 30 giorni, i manifest dopo 90) + copia su Cloudflare R2 con lock di 30 giorni; manifest dei file (27/09: 81.719 file) |
| Verifica restore | ✅ Automatica | Lunedì 04:30 UTC (`verifica-restore.yml`; 21/09 e 28/09 riuscite) | Ripristina l'ultimo dump in un MySQL usa e getta; apre una issue se non torna su |
| Codice sorgente | ✅ Git | Ad ogni push | GitHub |
| Droplet CompreFace | ✅ Backup DigitalOcean | Giornaliero, conservati 7 giorni (attivati il 23/09/2026, ~$7,20/mese) | Immagine intera del droplet. È l'unica copia della face collection, cioè degli esempi appresi: le foto di addestramento non vengono conservate (`CLAUDE.md` §12-ter), e senza backup un droplet perso significherebbe riaddestrare da capo |

Il bucket di backup ha una policy che nega la cancellazione ma non la
sovrascrittura. La copia su R2 sta fuori dal perimetro dell'account DigitalOcean
e viene scritta a ogni giro (il 1/10 verificata la copia del dump), ed è
**immutabile**: il bucket `sito-savino-backup-offsite` ha le regole di lock
`retention-db` (`db/`, 90 giorni) e `retention-media` (`media/`, 30 giorni),
gli stessi prefissi su cui scrivono i workflow. Provato il 1/10 su un file di
prova sotto `db/` con le credenziali di amministratore dell'account: la
sovrascrittura risponde 409 «The object is locked by the bucket policy» e la
cancellazione non toglie il file. Dettagli, setup e restore in
[`BACKUP.md`](../BACKUP.md).

La chiave dei workflow di backup (`backup-writer`) ha accesso completo
all'account Spaces: DigitalOcean non ammette chiavi limitate su un bucket con
policy. Chi la ottenesse potrebbe sovrascrivere i backup su Spaces e riscrivere
la policy, e il versioning di quel bucket non è verificato. È una scelta
consapevole (3/10/2026): la chiave sta solo nei secret di GitHub, il sito usa
una chiave limitata al proprio bucket e le copie su R2, bloccate, restano fuori
dalla sua portata.

### Sicurezza

| Misura | Stato |
|--------|-------|
| HTTPS (TLS) | ✅ Automatico (App Platform) |
| Accesso al DB | ✅ Trusted Sources: l'app e gli IP di sviluppo autorizzati, più il runner del backup per la durata del dump. L'IP di sviluppo cambia spesso: quando se ne aggiunge uno nuovo il vecchio va tolto, perché resta autorizzato anche dopo che la linea l'ha riassegnato a qualcun altro. L'endpoint chiede comunque utente, password e TLS |
| CompreFace | ✅ API solo dalla VPC (cloud firewall `compreface-fw`). SSH aperta a internet per scelta: solo chiave (password e keyboard-interactive disattivate), fail2ban attivo. Limitarla a un IP non regge, con un IP di sviluppo che cambia più volte al giorno |
| Content Security Policy | ✅ `SecurityHeadersMiddleware`: sito pubblico con nonce, senza `unsafe-inline`/`unsafe-eval`; il pannello li mantiene per Alpine |
| Terze parti prima del consenso | ✅ Font serviti dal sito; GA4 e Pixel Meta solo dopo il consenso; mappa e video incorporati click-to-load dal 26/09/2026 (`ContenutoIncorporato.vue`) |
| Indirizzo unico | ✅ `PortaSullIndirizzoDelSito`: GET su `www.` e `ondigitalocean.app` → 301 al dominio (POST, `api/*` e `/up` esclusi) |
| Sessioni cifrate | ✅ `SESSION_ENCRYPT` attivo |
| Cookie sicuri | ✅ `SESSION_SECURE_COOKIE` attivo |
| Debug disattivato | ✅ `APP_DEBUG=false` |
| Segreti nelle variabili d'ambiente | ✅ Tutti i segreti sono cifrati (`EV[1:…]`) in `.do/app.yaml`; in chiaro restano solo identificativi pubblici (DSN di Sentry, chiave pubblicabile di Stripe, client id e webhook id di PayPal). ⚠️ Nella cronologia del repository pubblico sono rimasti valori in chiaro: la `APP_KEY` è stata ruotata il 27/09/2026, la chiave di ActiveCampaign il 02/10/2026, e la chiave Spaces di allora non esiste più. Chiavi Spaces al 03/10/2026: quella dell'app (lettura e scrittura sul solo bucket del sito), una in sola lettura per le verifiche e `backup-writer` per i backup. Token API di DigitalOcean rinnovati il 03/10/2026, ciascuno coi soli permessi che gli servono. Il repository resta pubblico per scelta (03/10/2026): nella sua cronologia non restano segreti validi, e da privato perderebbe i minuti illimitati di GitHub Actions e SonarCloud gratuito |
| Error tracking | ✅ Sentry attivo dal 25/09/2026 (server ed errori JavaScript). Log a livello `warning` dal 03/10/2026 |
| Trust proxies | ✅ Configurato per App Platform |
| Health check | ✅ `/up` verifica database e cache; segnala (senza far fallire) uno scheduler fermo |

### Task schedulati

Definiti in `routes/console.php`, eseguiti dal componente **scheduler**
dedicato (vedi §3.3). Tutti i comandi ricorrenti tranne `scheduler:beat` hanno `withoutOverlapping()`
(lock condiviso via cache, valido anche fra istanze).

| Comando | Frequenza | Scopo |
|---------|-----------|-------|
| `scheduler:beat` | Ogni minuto | Battito letto dall'health check `/up`: rileva uno scheduler morto |
| `shop:sorveglia` | Ogni 5 minuti | Negozio/aste spenti, checkout (shop o aste) senza metodi di pagamento, aste senza Stripe per la verifica della carta, coda `default` ferma, PayPal (orario): email quando cambia (§9, Avvisi) |
| `lvf:sync` | Ogni ora | Calendario, risultati e classifica dal sito della Lega (fallimenti contati da `LvfSyncHealth`, alert ai Super Admin) |
| `cev:sync` | Ogni ora (:30) | Calendario, risultati e classifica del girone di Champions dal portale CEV |
| `news:importa-dal-vecchio-sito` | Tolto dallo scheduler il 2/10/2026 | Importava i comunicati dal vecchio WordPress (`wp-json`). Ultimo giro fatto a mano il 1/10 prima del cambio DNS: ora il dominio è questo sito e il comando non ha più una sorgente |
| `prezzi:registra` | Ogni ora (:05) | Storico dei prezzi per il barrato dei 30 giorni (Omnibus); butta la vetrina quando uno sconto comincia o finisce |
| `sitemap:generate` | Giornaliero (04:00) | Genera sitemap XML per SEO |
| `media:fix-remote-metadata --since="3 days ago"` | Giornaliero (04:30) | Ripassa Content-Type e Cache-Control sui file recenti caricati su Spaces |
| `social:sync-meta --days=90` | Giornaliero (03:30) | Insight Facebook/Instagram, max 120 chiamate |
| `analytics:sync-ga4 --days=90` | Giornaliero (05:00) | Serie giornaliera del traffico GA4 |
| `newsletter:retry-sync` | Giornaliero (05:15) | Ritenta su ActiveCampaign gli iscritti confermati non ancora sincronizzati (max 50) |
| `newsletter:allinea-disiscritti` | Giornaliero (05:15) | Porta sul sito le disiscrizioni fatte su ActiveCampaign e toglie nome e IP dei disiscritti |
| `RicostruisciLaCacheDellaGallery` (job) | Ogni ora (:17) | Rigenera la cache dell'archivio foto |
| `gallery:analyze --pending --limit=600 --force` | Ogni ora (:37) | Riconoscimento volti sulle foto non ancora analizzate |
| `volti:riconcilia-contatori` | Giornaliero (04:15) | Riallinea `players.ai_face_examples` a CompreFace |
| `activity-log:prune --days=180 --force` | Settimanale (domenica 00:00) | Pulisce log attività > 6 mesi |
| `consensi:pota` | Settimanale (domenica 00:00) | Prove di consenso cookie oltre i 24 mesi |
| `consensi:verifica` | Settimanale (lunedì 04:30) | Controlla che il registro dei consensi non sia stato alterato (righe incatenate) |
| `messaggi:pota` | Settimanale (domenica 00:00) | Messaggi e accrediti oltre i 24 mesi (dalla data del messaggio) |
| `model:prune` | Giornaliero | Carrelli scaduti da più di 7 giorni, iscrizioni alla newsletter non confermate entro 30 giorni, dichiarazioni di recesso oltre la conservazione (12 mesi; 10 anni se legate a un ordine) |
| `queue:prune-batches` / `queue:prune-failed` | Giornaliero | Batch rimasti aperti (72 h) e job falliti (30 giorni) |
| `carts:prune-expired` | Giornaliero (03:00) | Elimina i carrelli scaduti |
| `order:check-unpaid` | Ogni 10 minuti | Annulla gli ordini non pagati (carta e PayPal dopo un'ora, bonifico dopo i giorni di `shop.bank_transfer_expiry_days`), manda il promemoria del bonifico e rilascia lo stock |
| `auction:activate` | Ogni minuto | Attiva le aste programmate |
| `auction:close` | Ogni minuto | Chiude le aste scadute e notifica i vincitori |
| `auction:check-payments` | Oraria | Verifica i pagamenti dei vincitori d'asta |
| `sync:legavolley` | Giornaliero | Dati **simulati**, solo in ambienti **non** di produzione |

### Workflow GitHub Actions

| Workflow | Quando | Scopo |
|----------|--------|-------|
| `ci.yml` | Push su `main` (esclusi i soli `*.md`, `docs/**`, `Loghi/**`), PR verso `main`, lunedì 05:00 UTC | ESLint, Vitest, Pint, PHPStan, mappa delle dipendenze, PHPUnit, audit di Composer e npm; SonarCloud e soglie di copertura solo su PR e run schedulate; su `main` il job `deploy` applica lo spec |
| `backup-db.yml` | Ogni giorno 03:00 UTC | Dump cifrato del database (§9) |
| `backup-media.yml` | Domenica 04:00 UTC | Copia dei media di Spaces (§9) |
| `verifica-restore.yml` | Lunedì 04:30 UTC | Prova di ripristino dell'ultimo dump |
| `scansione-cookie.yml` | Lunedì 04:30 UTC | Playwright sul sito: aggiorna `database/data/cookie_rilevati.json` e va in rosso se qualcosa parte prima del consenso |
| `scansione-accessibilita.yml` | Lunedì 05:00 UTC | axe-core sul sito (sole letture) e sui flussi d'acquisto in CI; rosso sulle violazioni gravi (WCAG 2.1 AA, European Accessibility Act) |
| `sorveglianza-sito.yml` | Ogni ora, al minuto 17 (in pratica fra :20 e :35: GitHub non garantisce la puntualità) | `/up`, `/` e `/shop` da fuori DigitalOcean; in rosso se il sito non risponde o resta sopra i 6 s per due richieste di fila (§9, Avvisi) |

### Avvisi

Sei livelli, ciascuno per ciò che gli altri non possono vedere:

| Livello | Vede | Arriva a |
|---------|------|----------|
| `sorveglianza-sito.yml` (GitHub) | Sito irraggiungibile, giù del tutto o lento | Email di GitHub a chi ha modificato per ultimo il workflow |
| Monitoring DO sul database | CPU e memoria oltre il 90%, disco oltre l'80% | Referente tecnico (MV Consulting) |
| Webhook di Resend (`/api/webhooks/resend`) | Email ai clienti rimbalzate, segnalate come spam o non spedite | AvvisoTecnico → `allarmi@` |
| Alert di App Platform (`alerts:` nella spec) | Deploy fallito, dominio non attivo, container che riparte in ciclo, memoria del web | Referente tecnico (MV Consulting), con `doctl apps update-alert-destinations` |
| `App\Services\AvvisoTecnico` | Pianificatore fermo, comandi pianificati usciti con errore, job falliti (non coda `ai`), ordini da rivedere, contestazioni Stripe, `shop:sorveglia` | `AVVISI_EMAIL` (spec, livello d'app) via Resend |
| Sentry | Eccezioni del server e errori JavaScript del browser (via `/api/diagnostica`) | Regola del progetto: issue nuove, regressioni, alta priorità → `allarmi@` (email routing dell'account) |

Le regole di App Platform stanno nella spec, gli indirizzi no: dopo un
deploy che aggiunge una regola se ne leggono gli id con
`doctl apps list-alerts <app>` e si imposta la destinazione con
`doctl apps update-alert-destinations <app> <alert> --app-alert-destinations destinazioni.json`
(`{"emails": ["…"]}`). L'email deve essere di un membro del team DigitalOcean.

AvvisoTecnico manda in modo **sincrono** (non in coda: la coda ferma è uno dei
guasti da segnalare) e non lancia mai: un Resend irraggiungibile non deve far
fallire un webhook di pagamento. `invia()` restituisce un `EsitoAvviso`
(inviato, silenziato, senza destinatari, fallito): chi ricorda di aver già
avvisato — lo stato di `shop:sorveglia`, il silenziatore dell'health check e
quello dei job falliti — lo scrive o lo tiene solo se l'invio non è fallito,
così un Resend giù in quel momento non spegne l'avviso per sempre.
L'avviso sul pianificatore fermo parte con `defer()`, dopo la risposta di
`/up` (DigitalOcean aspetta 5 s, l'invio a Resend fino a 10).

**Lo stato degli avvisi sta nello store di cache `persistente`**
(`config/cache.php`, tabelle `cache_persistente` e `cache_persistente_locks`),
non in quello predefinito: `start.sh` esegue `cache:clear` a ogni avvio, e con
silenziatori e stato di `shop:sorveglia` lì dentro ogni rilascio rimandava gli
avvisi già mandati. Si legge con `AvvisoTecnico::memoria()`; nei test il
driver è `array` (`CACHE_PERSISTENTE_DRIVER` in `phpunit.xml`).

Il webhook di Resend avvisa solo per i destinatari con un ordine negli ultimi
30 giorni (email dell'ospite o dell'account), al massimo 10 avvisi l'ora: gli
altri indirizzi (newsletter, ricevute del recesso) li scrive chiunque, e
finiscono nel log senza l'indirizzo.

**Limiti di `sorveglianza-sito.yml`.** È una rete di sicurezza, non un
monitor: gira **una volta l'ora** (dal 26/09/2026), quindi un sito giù può
restare senza avviso fino a un'ora; GitHub manda un'email a **ogni** run
fallita e **nessuna** alla guarigione; la puntualità del cron non è garantita
(10-20 minuti di ritardo nelle ore di punta); e GitHub **disattiva i workflow
schedulati dopo 60 giorni** senza attività nel repository, senza avvisare chi
li riceve.

**Il controllo fitto è l'uptime check di DigitalOcean** `sito-savino-up`
(dal 01/10/2026, account Savino, `doctl --context savino monitoring uptime
list`): interroga `https://savinodelbenevolley.it/up` da `eu_west` e
`us_east`, fuori dall'app, e avvisa per email alla caduta e al ritorno. Tre
regole: `sito-savino-down` (giù da 2 minuti), `sito-savino-latenza` (oltre
6 s per 5 minuti, la soglia del workflow) e `sito-savino-ssl` (certificato in
scadenza entro 14 giorni). Le email vanno a `marco@mv-consulting.it`: come per
gli alert dell'app, DigitalOcean le manda solo a membri del team. Il workflow
orario resta come seconda strada, indipendente da DigitalOcean.

---

## 10. Account della società

I servizi del progetto che stanno su account della società. Le utenze di
accesso compaiono solo nella versione PDF consegnata alla società, non nel
repository, che è pubblico.

| Servizio | A cosa serve |
|----------|--------------|
| **DigitalOcean** (team «Savino del Bene Volley») | Hosting del sito, database, Spaces, droplet CompreFace, backup gestiti |
| **Stripe** (Pallavolo Scandicci Savino Del Bene SSDRL) | Pagamenti con carta, wallet e Klarna di shop e aste |
| **PayPal** (conto business della società) | Pagamenti PayPal di shop e aste |
| **Resend** (team `savinodelbene`) | Invio delle email del sito dal dominio `savinodelbenevolley.it` |
| **ActiveCampaign** (`savinodelbenevolley`) | Newsletter: liste, automazioni, Preference Center |
| **Meta** (portfolio «Savino Del Bene Volley», app «Savino Analytics») | Statistiche di Facebook e Instagram, Pixel |
| **DNS di `savinodelbenevolley.it`** | Gestito dall'IT della Spa: record del sito e della posta (Resend, DMARC) |

