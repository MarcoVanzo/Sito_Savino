# Documentazione Tecnica Infrastruttura — Savino Del Bene Volley

> Ultimo aggiornamento: 2 agosto 2026

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
      │ immagini CDN          │  ┌──────────────┐  ┌──────┴───┐ ┌┴────────┐│
      ├──────────────────────▶│  │  📦 Spaces   │  │ ⚙️ Worker│ │⏱️ Sched.││
      │                       │  │  S3 + CDN    │  │ 0.5GB    │ │ 0.5GB   ││
      │                       │  │  $5/mese     │  │ $5/mese  │ │ $5/mese ││
      │                       │  └──────────────┘  └────┬─────┘ └─────────┘│
      │                       │                         │ API              │
      │                       │                   ┌─────▼────────┐         │
      │                       │                   │ 🖥️ CompreFace│         │
      │                       │                   │ 4GB, $24/mese│         │
      │                       │                   └──────────────┘         │
      │                       └─────────────────────────────────────────────┘
```

---

## 2. Stack Tecnologico

### Backend

| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| **PHP** | 8.5.5 | Linguaggio backend |
| **Laravel** | 13.17.0 | Framework MVC |
| **Filament** | 3.3.54 | Pannello admin (CMS) |
| **Inertia.js** | 2.x | Bridge server↔client (SSR non attivo, vedi §6) |
| **MySQL** | 8.4 LTS | Database relazionale |
| **Apache** | (heroku buildpack) | Web server |
| **OPcache + JIT** | tracing 1255 | Compilazione PHP → codice nativo |

### Frontend

| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| **Vue.js** | 3.x | Framework UI (via Inertia) |
| **Vite** | (build tool) | Bundler assets |
| **Node.js** | ≥ 20 (`engines` in `package.json`; la CI usa Node 20) | Build-time (compilazione assets client) |
| **Tailwind CSS** | (via config) | Styling |

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
| **Stripe** (`stripe/stripe-php`) | Pagamenti shop (chiavi `STRIPE_*` non ancora nello spec, vedi §5) |

### Repository

| Dettaglio | Valore |
|----------|--------|
| GitHub | [MarcoVanzo/Sito_Savino](https://github.com/MarcoVanzo/Sito_Savino) |
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
- Se un job fallisce, **riprova fino a 3 volte** (con backoff di 30s, 60s)
- Si riavvia ogni **3600 secondi** (1 ora) per prevenire memory leak
- Timeout per singolo job: **120 secondi**

**Job processati:**

| Job | Trigger | Cosa fa |
|-----|---------|---------|
| `AnalyzeGalleryImageJob` | Upload foto nel CMS (Galleria) | Scarica l'immagine da S3, la invia a CompreFace per riconoscimento facciale, salva i risultati nel DB (coda `ai`) |
| `SyncNewsletterToActiveCampaign` | Iscrizione newsletter | Sincronizza il contatto su ActiveCampaign (coda `default`) |
| `UnsubscribeNewsletterFromActiveCampaign` | Disiscrizione o cancellazione di un iscritto | Porta la revoca su ActiveCampaign: status 2 sulla lista, oppure cancellazione del contatto se la richiesta è di cancellazione dati (coda `default`) |
| Mail transazionali | Ordini, aste, rimborsi | `Mail::to(...)->queue(...)` — richiedono un mailer configurato (vedi §5) |

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
| Connessione | Rete privata (non esposta a internet) |
| Nome cluster | `sito-savino-db` |

**Dati contenuti (principali):**

| Categoria | Tabelle | Esempi |
|-----------|---------|--------|
| Contenuti | posts, pages, categories, hero_slides | News, pagine statiche, slider |
| Squadra | players, staff_members, rosters, player_stats | Rose, statistiche, organigramma |
| Partite | games, seasons, teams | Calendario, risultati, classifiche |
| Galleria | gallery_events, gallery_images | Eventi foto, analisi facciale |
| Shop | products, product_categories, orders, stock_movements | Prodotti, ordini, magazzino |
| Sponsor | sponsors | Loghi e link partner |
| Sistema | users, activity_log, jobs, sessions, menu_items | Utenti, log, code, navigazione |
| Media | media (Spatie) | Metadati file caricati |

**Backup:** Automatici giornalieri gestiti da DigitalOcean (inclusi nel costo).

---

### 3.5 📦 Spaces S3 + CDN — $5/mese

**Cosa fa:** Storage dei file (immagini, media, documenti). È un servizio di object storage compatibile con Amazon S3, quindi Laravel lo usa come se fosse AWS S3.

| Spec | Valore |
|------|--------|
| Bucket | `sito-savino-assets-2026` |
| Region | `fra1` (Frankfurt) |
| Storage incluso | 250 GB |
| Transfer incluso | 1 TB/mese |
| CDN | Attivo (incluso nel costo) |
| URL diretto | `sito-savino-assets-2026.fra1.digitaloceanspaces.com` |
| URL CDN | `sito-savino-assets-2026.fra1.cdn.digitaloceanspaces.com` |
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

**Come funziona il CDN:**
1. L'admin carica un'immagine nel CMS
2. Laravel la salva su Spaces via API S3
3. Spatie Media Library genera le conversioni (thumbnail, webp)
4. Il sito pubblico richiede l'immagine dal CDN
5. Il CDN serve la copia più vicina all'utente (cache edge)

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
| IP | 157.230.98.6 |
| Porta API | 8000 |
| ID Droplet | 580932690 |

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
7. Worker salva i risultati nel DB (tabella gallery_images)
```

**Perché 4GB RAM:** CompreFace è un'applicazione Java (Spring Boot) che carica in memoria modelli di deep learning per il riconoscimento facciale. Il modello + JVM + API server richiedono circa 2-3 GB di RAM operativa.

---

## 4. Flusso delle Richieste

### 4.1 Visita sito pubblico (es. `/news`)

```
Utente → HTTPS → App Web → Cache check (CachePublicResponse)
                              │
                    ┌─────────┴──────────┐
                    │                    │
              Cache HIT             Cache MISS
              (< 5ms)                   │
                    │              Query MySQL
                    │              Render Inertia (no SSR)
                    │              Salva in cache
                    │                    │
                    ▼                    ▼
              HTML response        HTML response
                    │
              Immagini → CDN Spaces → Browser
```

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
10. Worker                    → UPDATE gallery_images con risultati
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
| `APP_URL` | URL pubblico | URL base dell'applicazione |
| `APP_LOCALE` | `it` | Lingua predefinita |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `${sito-savino-db.HOSTNAME}` | Host DB (injected da DO) |
| `DB_PORT` | `${sito-savino-db.PORT}` | Porta DB (injected da DO) |
| `DB_DATABASE` | `${sito-savino-db.DATABASE}` | Nome database |
| `DB_USERNAME` | `${sito-savino-db.USERNAME}` | Utente DB |
| `DB_PASSWORD` | 🔒 `${sito-savino-db.PASSWORD}` | Password DB |
| `CACHE_STORE` | `database` | Driver cache (tabella MySQL `cache`) |
| `SESSION_DRIVER` | `database` | Sessioni salvate in MySQL |
| `SESSION_ENCRYPT` | (attivo) | Sessioni cifrate |
| `SESSION_SECURE_COOKIE` | (attivo) | Cookie solo HTTPS |
| `QUEUE_CONNECTION` | `database` | Code via tabella MySQL `jobs` |
| `FILESYSTEM_DISK` | `s3` | Storage file su Spaces |
| `MEDIA_DISK` | `s3` | Media Library su Spaces |
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
| `PREVIEW_AUTH_USER` / `PREVIEW_AUTH_PASS` | 🔒 secret | Basic auth su tutto il sito (fase di pre-lancio) |
| `COMPREFACE_HOST` | `http://10.114.0.3:8000` | URL server CompreFace (rete privata VPC) |
| `COMPREFACE_KEY` | in chiaro nello spec | API key CompreFace — attualmente il valore di default `0000…0001`, da rigenerare e cifrare |
| `ACTIVECAMPAIGN_URL` / `ACTIVECAMPAIGN_LIST_ID` | in chiaro | Endpoint e lista newsletter |
| `ACTIVECAMPAIGN_API_KEY` | ⚠️ in chiaro nello spec | Marcata `SECRET` ma il valore **non è cifrato** (niente formato `EV[1:…]`): va ruotata e ricifrata |

> ⚠️ **Variabili mancanti nello spec** (`.do/app.yaml`): nessuna variabile
> `MAIL_*` / `RESEND_API_KEY`, `STRIPE_*`, `PAYPAL_*`. Poiché il deploy applica
> lo spec del repository, eventuali valori aggiunti a mano dal pannello DO
> vengono sovrascritti ad ogni rilascio. Vanno aggiunti allo spec come `SECRET`.

### Worker e Scheduler

Worker e scheduler replicano le stesse variabili del web (incluse `AWS_*`,
`COMPREFACE_*`, `ACTIVECAMPAIGN_*`, Sentry), con in più `APP_URL` esplicito:
senza, le email in coda (es. link di checkout al vincitore d'asta) uscivano
con host `http://localhost`.

---

## 6. Build e Deploy

### Pipeline di deploy

```
git push main → GitHub Actions (CI: lint, PHPStan, test) → job "deploy"
              → digitalocean/app_action con lo spec .do/app.yaml → Build Web + Worker → ACTIVE
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

### Build Worker

```bash
composer install --optimize-autoloader --no-dev
```

Solo dipendenze PHP (il worker non serve frontend).

### Avvio Web (`start.sh`)

| Step | Comando | Scopo |
|------|---------|-------|
| 1/6 | `migrate --force` | Applica nuove migrazioni DB (senza `\|\| true`: uno schema disallineato deve fermare l'avvio) |
| 2/6 | `db:seed SiteSettingSeeder` + `db:seed CorporateGovernanceSeeder` | Seeder idempotenti: garantiscono le impostazioni di base (un fallimento ferma l'avvio) |
| 3/6 | `storage:link` | Symlink `public/storage → storage/app/public` |
| 4/6 | `cache:clear` | Svuota cache del deploy precedente (cancella anche il battito dello scheduler) |
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
| `CachePublicResponse` middleware | Full-page HTML cache | Solo sito pubblico (esclude `/admin`) |
| Spaces CDN | Edge cache immagini | Immagini/media |

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
| 6 | 📦 Spaces + CDN | 250 GB, fra1 | **$5.00** |
| | | **TOTALE** | **$64.23/mese** |
| | | | **~€59/mese** |
| | | | **~€710/anno** |

---

## 9. Backup e Sicurezza

### Backup

| Componente | Backup | Frequenza | Gestione |
|-----------|--------|-----------|----------|
| Database MySQL | ✅ Automatico | Giornaliero | DigitalOcean managed |
| Spaces S3 | ✅ Persistente | In tempo reale | Object storage durabile |
| Codice sorgente | ✅ Git | Ad ogni push | GitHub |
| Droplet CompreFace | ⚠️ Manuale | Da configurare | Snapshot DigitalOcean |

### Sicurezza

| Misura | Stato |
|--------|-------|
| HTTPS (TLS) | ✅ Automatico (App Platform) |
| DB su rete privata | ✅ Non esposto a internet |
| Sessioni cifrate | ✅ `SESSION_ENCRYPT` attivo |
| Cookie sicuri | ✅ `SESSION_SECURE_COOKIE` attivo |
| Debug disattivato | ✅ `APP_DEBUG=false` |
| Secret in env vars | ⚠️ Quasi tutti cifrati (`EV[1:…]`) in `.do/app.yaml`, ma `ACTIVECAMPAIGN_API_KEY` è in chiaro nel repository pubblico: va **ruotata** e ricifrata. Anche `COMPREFACE_KEY` è il default in chiaro |
| Trust proxies | ✅ Configurato per App Platform |
| Log level | ✅ `error` (niente info/debug in prod) |
| Health check | ✅ `/up` verifica database e cache; segnala (senza far fallire) uno scheduler fermo |
| OPcache validate_timestamps | ✅ Disabilitato (previene code injection) |

### Task schedulati

Definiti in `routes/console.php`, eseguiti dal componente **scheduler**
dedicato (vedi §3.3). Tutti i comandi ricorrenti hanno `withoutOverlapping()`
(lock condiviso via cache, valido anche fra istanze).

| Comando | Frequenza | Scopo |
|---------|-----------|-------|
| `scheduler:beat` | Ogni minuto | Battito letto dall'health check `/up`: rileva uno scheduler morto |
| `lvf:sync` | Ogni ora | Calendario, risultati e classifica dal sito della Lega (fallimenti contati da `LvfSyncHealth`, alert ai Super Admin) |
| `sitemap:generate` | Giornaliero (04:00) | Genera sitemap XML per SEO |
| `media:fix-remote-metadata --since="3 days ago"` | Giornaliero (04:30) | Ripassa Content-Type e Cache-Control sui file recenti caricati su Spaces |
| `activity-log:prune --days=180` | Settimanale | Pulisce log attività > 6 mesi |
| `model:prune` | Giornaliero | Pulisce modelli scaduti |
| `carts:prune-expired` | Giornaliero (03:00) | Elimina i carrelli scaduti |
| `order:check-unpaid` | Ogni 10 minuti | Annulla ordini non pagati e rilascia lo stock |
| `auction:activate` | Ogni minuto | Attiva le aste programmate |
| `auction:close` | Ogni minuto | Chiude le aste scadute e notifica i vincitori |
| `auction:check-payments` | Oraria | Verifica i pagamenti dei vincitori d'asta |
| `sync:legavolley` | Giornaliero | Dati **simulati**, solo in ambienti **non** di produzione |
