# Documentazione Tecnica Infrastruttura — Savino Del Bene Volley

> Ultimo aggiornamento: 23 settembre 2026
>
> Il sito oggi risponde solo su `seashell-app-47mmf.ondigitalocean.app`. Il passaggio
> di `savinodelbenevolley.it` è fissato al **1 ottobre 2026** e ha una procedura sua:
> [`docs/GO_LIVE.md`](GO_LIVE.md) (`php artisan verifica:lancio` dice cosa manca).
>
> La versione stampabile `docs/INFRASTRUCTURE.html` si rigenera da questo file con
> `python3 scripts/genera-infrastructure-html.py`: non si modifica a mano.

---

## Indice

1. [Panoramica Architettura](#1-panoramica-architettura)
2. [Stack Tecnologico](#2-stack-tecnologico)
3. [Servizi DigitalOcean — Dettaglio](#3-servizi-digitalocean--dettaglio)
4. [Flusso delle Richieste](#4-flusso-delle-richieste)
5. [Variabili d'Ambiente](#5-variabili-dambiente)
6. [Build e Deploy](#6-build-e-deploy)
7. [Performance e OPcache](#7-performance-e-opcache)
8. [Costi](#8-costi)
9. [Backup e Sicurezza](#9-backup-e-sicurezza)

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
| **Laravel** | 13.17.0 | Framework MVC |
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
| **Node.js** | ≥ 20 (`engines` in `package.json`; la CI usa Node 20) | Build-time (compilazione assets client) |
| **Tailwind CSS** | 3.4 | Styling |
| **Font** | Montserrat, Playfair Display | Serviti dal sito (`public/fonts`, `@font-face` in `resources/css/app.css`), non dal CDN di Google |

### Servizi e Librerie

| Tecnologia | Ruolo |
|-----------|-------|
| **Spatie Media Library** | Gestione media con conversioni automatiche |
| **CompreFace** | Riconoscimento facciale (self-hosted) |
| **Resend** | Invio email transazionali (pacchetto installato, ⚠️ **non ancora configurato in produzione**: senza `MAIL_MAILER=resend` + `RESEND_API_KEY` il mailer di default è `log`) |
| **Ziggy** | Routing Laravel → JavaScript |
| **Predis** | Client Redis (pronto per futuro uso) |
| **Spatie Translatable** | Contenuti multilingua |
| **Spatie Sitemap** | Generazione sitemap SEO |
| **Sentry** | Error tracking (web, worker e scheduler; attivo solo quando `SENTRY_LARAVEL_DSN` è valorizzato — oggi è vuoto, quindi spento) |
| **PayPal** (REST API, `PayPalPaymentService`) | Pagamenti shop e aste, modalità `live`; `php artisan paypal:verifica` controlla credenziali e webhook |
| **Stripe** (`stripe/stripe-php`) | Pagamenti shop — chiavi `STRIPE_*` non nello spec: il metodo non viene offerto al checkout (`PaymentGateway::configurato()`) |
| **ActiveCampaign** | Newsletter (iscrizioni e revoche via coda) |
| **GA4 Data API** / **Meta Graph API** | Analytics del pannello (sito, Facebook, Instagram) — vedi `docs/ANALYTICS.md` |
| **Lega Volley Femminile** | Calendario, risultati, classifica e tabellini, letti dalle pagine pubbliche (`app/Services/Lvf/`) |

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
                                          battito dello scheduler, vedi health check)
   php artisan gallery:riscalda-cache   → Accoda la ricostruzione dell'archivio foto
                                          (12.000 foto): non la paga il primo visitatore
5. php artisan config:cache             → Solo se le credenziali AWS sono presenti
                                          (altrimenti config:clear, per non congelare
                                          valori S3 vuoti)
   php artisan route:cache / view:cache / event:cache
6. php artisan filament:optimize        → Cachea componenti Filament
7. heroku-php-apache2 -i opcache.ini    → Avvia Apache con OPcache tuning
```

**Health check** (`.do/app.yaml`): App Platform interroga `/up` via HTTP
(`App\Listeners\VerifyApplicationHealth`), che verifica database e cache.
Uno scheduler fermo viene segnalato ma **non** fa fallire il check (riavviare
il web non lo risolverebbe). `initial_delay_seconds: 120` è vincolante: dopo
`cache:clear` il battito dello scheduler torna solo al giro successivo, e un
delay più corto metterebbe l'istanza in ciclo di riavvio.

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
- Se un job fallisce, **riprova fino a 3 volte**; attese e timeout li decide il
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
| Mail transazionali | Ordini, aste, rimborsi | `Mail::to(...)->queue(...)` — oggi finiscono nel log: nessun mailer configurato (vedi §5) |

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
  condivisa: senza, l'health check del web resterebbe in rosso per un minuto
  a ogni riavvio di questo componente.
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
| Connessione | Endpoint pubblico TLS (porta 25060) filtrato dalle **Trusted Sources**: l'app `sito-savino` e l'IP di sviluppo. Il backup aggiunge l'IP del runner GitHub per la durata del dump e lo rimuove alla fine, anche su errore |
| Nome cluster | `sito-savino-db` |

**Dati contenuti (principali):**

| Categoria | Tabelle | Esempi |
|-----------|---------|--------|
| Contenuti | posts, pages, categories, hero_slides, site_settings | News (941 storiche + import orario da WordPress), pagine CMS, impostazioni |
| Squadra | players, staff_members, rosters, player_stats, game_player_stats | Rose, statistiche stagionali ricostruite dai tabellini |
| Partite | games, seasons, teams, team_lvf_club_ids | Calendario, risultati, classifiche (sync Lega) |
| Galleria | gallery_events, gallery_images, gallery_image_person | Eventi foto, tag delle atlete (manuali e AI) |
| Shop | products, product_categories, orders, coupons, shipping_zones, stock_movements | Prodotti, ordini, codici sconto, fasce di peso |
| Aste | auctions, bids | Aste benefiche e offerte |
| Sponsor | sponsors | Loghi e link partner |
| Analytics | web_analytics_daily, social_insights_daily, social_accounts | Serie giornaliere GA4 e Meta (`docs/ANALYTICS.md`) |
| Privacy | consensi_cookie, contact_messages | Prove di consenso (12 mesi), messaggi e accrediti (24 mesi) |
| Sistema | users, activity_logs, jobs, job_batches, cache, sessions, menu_items | Utenti, log, code, navigazione |
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
| Media Library | Conversioni Spatie (thumbnail, webp, responsive) |

**Come arriva un'immagine al browser:**
1. L'admin carica un'immagine nel CMS
2. Laravel la salva su Spaces via API S3
3. Spatie Media Library genera le conversioni in coda (thumbnail, webp)
4. Il sito pubblico la chiede **direttamente all'origine di Frankfurt**: gli
   indirizzi nascono da `AWS_URL`, che punta all'endpoint non-CDN. Il
   `Cache-Control` che `media:fix-remote-metadata` scrive sui file vale per la
   cache del browser, non per un edge.

Passare al CDN significa cambiare `AWS_URL` sullo spec (web, worker e
scheduler) e verificare che l'endpoint CDN sia attivo sul bucket; gli indirizzi
già salvati in chiaro nei contenuti (HTML delle notizie) resterebbero
sull'origine.

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
`COMPREFACE_KEY`. Le regole sugli esempi di addestramento (volto minimo 90 px,
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

**Perché 4GB RAM:** CompreFace è un'applicazione Java (Spring Boot) che carica in memoria modelli di deep learning per il riconoscimento facciale. Il modello + JVM + API server richiedono circa 2-3 GB di RAM operativa.

---

## 4. Flusso delle Richieste

### 4.1 Visita sito pubblico (es. `/news`)

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
5. App Web                    → Risponde "Foto caricata ✅"
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
| `APP_URL` | `https://${APP_DOMAIN}` | URL base. Segue il dominio della spec: per questo `domains:` si aggiunge solo il giorno del passaggio (`docs/GO_LIVE.md`) |
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
| `LOG_LEVEL` | `error` | Solo errori: i log DO sono effimeri, servono da contesto per Sentry |
| `SENTRY_LARAVEL_DSN` | `""` (vuoto) | Error tracking — **spento** finché il DSN non viene valorizzato (cifrato, vedi commento nello spec) |
| `SENTRY_ENVIRONMENT` | `production` | Ambiente riportato a Sentry |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.1` | Campionamento performance tracing |
| `PREVIEW_AUTH_ENABLED` | `false` | Basic auth di pre-lancio **spenta**: il sito è pubblico |
| `PREVIEW_AUTH_USER` / `PREVIEW_AUTH_PASS` | 🔒 secret | Credenziali pronte per richiuderlo (con la protezione accesa e i segreti vuoti il sito risponde 503) |
| `COMPREFACE_HOST` | `http://10.114.0.3:8000` | URL server CompreFace (rete privata VPC) |
| `COMPREFACE_KEY` | 🔒 secret cifrato | API key CompreFace |
| `ACTIVECAMPAIGN_URL` / `ACTIVECAMPAIGN_LIST_ID` | in chiaro | Endpoint e lista newsletter |
| `ACTIVECAMPAIGN_API_KEY` | 🔒 secret cifrato | Oggi cifrata (`EV[1:…]`); il commento nello spec la dà ancora da **ruotare**, perché il valore è stato in chiaro nel repository |
| `GA4_SERVICE_ACCOUNT_JSON` | 🔒 secret cifrato | Service account Google (base64) per la GA4 Data API |
| `META_APP_ID` / `META_CONFIG_ID` | in chiaro | App Meta e configurazione Login for Business (non segreti) |
| `META_APP_SECRET` | 🔒 secret, **a livello di app** | Unica variabile dichiarata sopra i componenti: vale per web, worker e scheduler |
| `PAYPAL_CLIENT_ID` / `PAYPAL_MODE` | in chiaro / `live` | Credenziale pubblica e ambiente |
| `PAYPAL_CLIENT_SECRET` | 🔒 secret cifrato | |
| `PAYPAL_WEBHOOK_ID` | in chiaro | Entra nella verifica della firma: al cambio di dominio si **modifica l'URL** del webhook esistente, non se ne crea uno nuovo |

> ⚠️ **Variabili ancora assenti dallo spec** (`.do/app.yaml`):
> - `MAIL_MAILER` / `RESEND_API_KEY` / `MAIL_FROM_*`: senza, `config/mail.php`
>   cade su `log` e **nessuna email esce** (ordini, aste, rimborsi, reset password).
>   Oltre alle variabili serve il DKIM di Resend sul DNS della Spa, che pubblica
>   `DMARC p=reject`: procedura in `docs/GO_LIVE.md` §1.
> - `STRIPE_*`: il gateway resta nascosto al checkout finché non ci sono.
>
> Il deploy applica lo spec del repository: un valore aggiunto solo dal pannello
> DO viene cancellato al rilascio successivo. I segreti si scrivono dal pannello
> con "Encrypt" e si ricopiano nello spec nella forma `EV[1:…]`.

### Worker e Scheduler

Web, worker e scheduler sono **tre ambienti distinti**: una variabile scritta solo
sotto `services:` non arriva alla coda né allo scheduler. Worker e scheduler
ripetono quindi le variabili del web (`APP_KEY`, `APP_URL`, `AWS_*`,
`FILAMENT_FILESYSTEM_DISK`, `COMPREFACE_*`, `ACTIVECAMPAIGN_*`, `GA4_*`, `META_*`,
`PAYPAL_*`, Sentry).

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
git push main → GitHub Actions (CI: lint, PHPStan, test) → job "deploy"
              → digitalocean/app_action con lo spec .do/app.yaml → Build Web + Worker + Scheduler → ACTIVE
```

Lo spec `.do/app.yaml` del repository **sovrascrive** la configurazione dell'app
ad ogni deploy: ogni variabile d'ambiente deve stare lì.

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
`schedule:work` manda l'output dei comandi in `/dev/null` e Sentry è spento: un
comando che fallisce ogni notte non lascia traccia nei log di App Platform.

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

### Utilizzo reale in produzione (misurato il 2 luglio 2026)

| Risorsa | Allocata | Usata | % |
|---------|----------|-------|---|
| OPcache memory | 128 MB | 69 MB | 54% |
| Interned strings | 16 MB | 10 MB | 60% |
| JIT buffer | 32 MB | <1 MB | 3% |
| File cachati | 16.229 max | 1.640 | 10% |
| Hit rate | — | 90.3% | — |

### Benchmark TTFB (cache calde, 2 luglio 2026)

> Misure di luglio, con `curl` senza cookie: per il sito pubblico sono
> probabilmente cache HIT di `CachePublicResponse` (§4.1), non il tempo di una
> navigazione reale. Da rimisurare dopo il passaggio del dominio.

| Pagina | TTFB |
|--------|------|
| CMS Admin `/admin/login` | **0.18s** |
| Sito Home `/` | **0.15s** |
| Sito Stagione `/stagione` | **0.17s** |
| Sito News `/news` | **0.11s** |
| TCP connect (Italia→Frankfurt) | 0.017s |
| TLS handshake | 0.037s |

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
| 7 | 💾 Backup droplet CompreFace | settimanali, 20% del droplet | **$4.80** |
| | | **TOTALE** | **$69.03/mese** |
| | | | **~€63/mese** |
| | | | **~€760/anno** |

Cifre di listino: la fatturazione non è leggibile con il token di sola lettura
(403), quindi il totale non è confrontato con una fattura.

Voci variabili, fuori dal totale:
- **Spaces oltre i 250 GB** ($0.02/GB): il bucket di backup
  `sito-savino-backups` sta nello stesso abbonamento del bucket degli asset, e i
  media vi sono copiati per intero. Con dodicimila foto più le conversioni, la
  somma dei due bucket va controllata dal pannello prima di darla per inclusa.
- **Cloudflare R2**: 10 GB gratuiti, poi a consumo; contiene dump e media.
- **GitHub Actions**: gratuito, il repository è pubblico. Cookiebot è stato scartato
(~30 €/mese per 1958 pagine): consenso e scansione dei cookie sono fatti in casa.

---

## 9. Backup e Sicurezza

### Backup

| Componente | Backup | Frequenza | Gestione |
|-----------|--------|-----------|----------|
| Database MySQL | ✅ Managed | Giornaliero, ultimi 7 giorni (verificato 23/09: 8 copie, ~0,56 GB) | DigitalOcean |
| Database MySQL | ✅ Dump GPG | Giornaliero 03:00 UTC (`backup-db.yml`) | Bucket `sito-savino-backups` (90 giorni) + copia su Cloudflare R2 |
| Media Spaces | ✅ Copia | Settimanale, domenica 04:00 UTC (`backup-media.yml`) | Bucket `sito-savino-backups` (30 giorni) + copia su Cloudflare R2, con manifest dei file (20/09: 81.308 file) |
| Verifica restore | ✅ Automatica | Lunedì 04:30 UTC (`verifica-restore.yml`) | Ripristina l'ultimo dump in un MySQL usa e getta; apre una issue se non torna su |
| Codice sorgente | ✅ Git | Ad ogni push | GitHub |
| Droplet CompreFace | ✅ Backup DigitalOcean | Settimanale (attivati il 23/09/2026, ~$4,80/mese) | Immagine intera del droplet. È l'unica copia della face collection, cioè degli esempi appresi: le foto di addestramento non vengono conservate (`CLAUDE.md` §12-ter), e senza backup un droplet perso significherebbe riaddestrare da capo |

Il bucket di backup ha una policy che nega la cancellazione ma non la
sovrascrittura. La copia su R2 sta fuori dal perimetro dell'account DigitalOcean
e i giri di settembre la scrivono davvero (`R2_CHIAVI_PRESENTI: true`); che sia
anche **immutabile** dipende dal bucket lock, che si configura su Cloudflare e
da qui non è verificabile. Dettagli, setup e restore in [`BACKUP.md`](../BACKUP.md).

### Sicurezza

| Misura | Stato |
|--------|-------|
| HTTPS (TLS) | ✅ Automatico (App Platform) |
| Accesso al DB | ✅ Trusted Sources: l'app e l'IP di chi sviluppa, più il runner del backup per la durata del dump. L'IP di sviluppo cambia spesso: quando se ne aggiunge uno nuovo il vecchio va tolto, perché resta autorizzato anche dopo che la linea l'ha riassegnato a qualcun altro. L'endpoint chiede comunque utente, password e TLS |
| CompreFace | ✅ API solo dalla VPC (cloud firewall `compreface-fw`). SSH aperta a internet per scelta: solo chiave (password e keyboard-interactive disattivate), fail2ban attivo. Limitarla a un IP non regge, con un IP di sviluppo che cambia più volte al giorno |
| Content Security Policy | ✅ `SecurityHeadersMiddleware`: sito pubblico con nonce, senza `unsafe-inline`/`unsafe-eval`; il pannello li mantiene per Alpine |
| Terze parti prima del consenso | ✅ Font serviti dal sito; GA4 e Pixel Meta solo dopo il consenso. ⚠️ Mappa e video incorporati partono ancora prima (`docs/PRIVACY.md`) |
| Sessioni cifrate | ✅ `SESSION_ENCRYPT` attivo |
| Cookie sicuri | ✅ `SESSION_SECURE_COOKIE` attivo |
| Debug disattivato | ✅ `APP_DEBUG=false` |
| Secret in env vars | ✅ Tutti cifrati (`EV[1:…]`) in `.do/app.yaml`. ⚠️ `ACTIVECAMPAIGN_API_KEY` è stata in chiaro nel repository pubblico: va ancora **ruotata** |
| Error tracking | ⚠️ Sentry spento (`SENTRY_LARAVEL_DSN` vuoto) e `LOG_LEVEL=error`: i warning non si vedono da nessuna parte |
| Trust proxies | ✅ Configurato per App Platform |
| Health check | ✅ `/up` verifica database e cache; segnala (senza far fallire) uno scheduler fermo |

### Task schedulati

Definiti in `routes/console.php`, eseguiti dal componente **scheduler**
dedicato (vedi §3.3). Tutti i comandi ricorrenti hanno `withoutOverlapping()`
(lock condiviso via cache, valido anche fra istanze).

| Comando | Frequenza | Scopo |
|---------|-----------|-------|
| `scheduler:beat` | Ogni minuto | Battito letto dall'health check `/up`: rileva uno scheduler morto |
| `lvf:sync` | Ogni ora | Calendario, risultati e classifica dal sito della Lega (fallimenti contati da `LvfSyncHealth`, alert ai Super Admin) |
| `news:importa-dal-vecchio-sito` | Ogni ora | Comunicati pubblicati sul vecchio WordPress (`wp-json`); si spegne da solo il 2/10/2026 (`services.vecchio_sito.leggibile_fino_a`) |
| `sitemap:generate` | Giornaliero (04:00) | Genera sitemap XML per SEO |
| `media:fix-remote-metadata --since="3 days ago"` | Giornaliero (04:30) | Ripassa Content-Type e Cache-Control sui file recenti caricati su Spaces |
| `social:sync-meta --days=90` | Giornaliero (03:30) | Insight Facebook/Instagram, max 120 chiamate |
| `analytics:sync-ga4 --days=90` | Giornaliero (05:00) | Serie giornaliera del traffico GA4 |
| `RicostruisciLaCacheDellaGallery` (job) | Ogni ora (:17) | Rigenera la cache dell'archivio foto |
| `gallery:analyze --pending --limit=600` | Ogni ora (:37) | Riconoscimento volti sulle foto non ancora analizzate |
| `volti:riconcilia-contatori` | Giornaliero (04:15) | Riallinea `players.ai_face_examples` a CompreFace |
| `activity-log:prune --days=180` | Settimanale | Pulisce log attività > 6 mesi |
| `consensi:pota` | Settimanale | Prove di consenso cookie oltre i 12 mesi |
| `messaggi:pota` | Settimanale | Messaggi e accrediti oltre i 24 mesi (dalla data del messaggio) |
| `model:prune` | Giornaliero | Pulisce modelli scaduti |
| `queue:prune-batches` / `queue:prune-failed` | Giornaliero | Batch rimasti aperti (72 h) e job falliti (30 giorni) |
| `carts:prune-expired` | Giornaliero (03:00) | Elimina i carrelli scaduti |
| `order:check-unpaid` | Ogni 10 minuti | Annulla ordini non pagati e rilascia lo stock |
| `auction:activate` | Ogni minuto | Attiva le aste programmate |
| `auction:close` | Ogni minuto | Chiude le aste scadute e notifica i vincitori |
| `auction:check-payments` | Oraria | Verifica i pagamenti dei vincitori d'asta |
| `sync:legavolley` | Giornaliero | Dati **simulati**, solo in ambienti **non** di produzione |

### Workflow GitHub Actions

| Workflow | Quando | Scopo |
|----------|--------|-------|
| `ci.yml` | Push e PR; lunedì 05:00 UTC | Lint, PHPStan, test, SonarCloud; su `main` il job `deploy` applica lo spec |
| `backup-db.yml` | Ogni giorno 03:00 UTC | Dump cifrato del database (§9) |
| `backup-media.yml` | Domenica 04:00 UTC | Copia dei media di Spaces (§9) |
| `verifica-restore.yml` | Lunedì 04:30 UTC | Prova di ripristino dell'ultimo dump |
| `scansione-cookie.yml` | Lunedì 04:30 UTC | Playwright sul sito: aggiorna `database/data/cookie_rilevati.json` e va in rosso se qualcosa parte prima del consenso |
