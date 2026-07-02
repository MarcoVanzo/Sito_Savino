# Documentazione Tecnica Infrastruttura — Savino Del Bene Volley

> Ultimo aggiornamento: 2 luglio 2026

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
      │                       │  └──────┬───────┘     └──────▲───────┘     │
      │                       │         │ upload              │ job queue   │
      │                       │         ▼                     │             │
      │ immagini CDN          │  ┌──────────────┐     ┌──────┴───────┐     │
      ├──────────────────────▶│  │  📦 Spaces   │     │  ⚙️ Worker  │     │
      │                       │  │  S3 + CDN    │     │  0.5GB, $5/mo│     │
      │                       │  │  $5/mese     │     └──────┬───────┘     │
      │                       │  └──────────────┘            │ API         │
      │                       │                        ┌─────▼────────┐    │
      │                       │                        │ 🖥️ CompreFace│    │
      │                       │                        │ 4GB, $24/mese│    │
      │                       │                        └──────────────┘    │
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
| **Inertia.js** | 2.x | Bridge server↔client (SSR) |
| **MySQL** | 8.4 LTS | Database relazionale |
| **Apache** | (heroku buildpack) | Web server |
| **OPcache + JIT** | tracing 1255 | Compilazione PHP → codice nativo |

### Frontend

| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| **Vue.js** | 3.x | Framework UI (via Inertia) |
| **Vite** | (build tool) | Bundler assets |
| **Node.js** | 24.14.0 | Build-time (SSR + compilazione) |
| **Tailwind CSS** | (via config) | Styling |

### Servizi e Librerie

| Tecnologia | Ruolo |
|-----------|-------|
| **Spatie Media Library** | Gestione media con conversioni automatiche |
| **CompreFace** | Riconoscimento facciale (self-hosted) |
| **Resend** | Invio email transazionali |
| **Ziggy** | Routing Laravel → JavaScript |
| **Predis** | Client Redis (pronto per futuro uso) |
| **Spatie Translatable** | Contenuti multilingua |
| **Spatie Sitemap** | Generazione sitemap SEO |

### Repository

| Dettaglio | Valore |
|----------|--------|
| GitHub | [MarcoVanzo/Sito_Savino](https://github.com/MarcoVanzo/Sito_Savino) |
| Branch produzione | `main` |
| Deploy | Automatico su push a `main` |

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
| `/` | Sito pubblico (home, news, stagione, squadra, shop...) | Inertia + Vue.js (SSR) |
| `/admin` | CMS pannello amministrativo | Filament 3 (Livewire) |

**Processo di avvio** (`start.sh`):

```
1. php artisan migrate --force          → Aggiorna schema DB
2. php artisan storage:link             → Crea symlink storage/
3. php artisan cache:clear              → Pulisce cache vecchia
4. php artisan config:cache             → Cachea configurazione
5. php artisan route:cache              → Cachea le route
6. php artisan view:cache               → Compila Blade templates
7. php artisan event:cache              → Cachea event listeners
8. php artisan filament:optimize        → Cachea componenti Filament
9. heroku-php-apache2 -i opcache.ini    → Avvia Apache con OPcache tuning
```

---

### 3.2 ⚙️ App Worker (code) — $5/mese

**Cosa fa:** Processa i job in background. Resta in ascolto sulla tabella `jobs` nel database e quando trova un nuovo job, lo esegue senza bloccare il browser dell'utente.

| Spec | Valore |
|------|--------|
| Slug | `apps-s-1vcpu-0.5gb` |
| CPU | 1 shared vCPU |
| RAM | 512 MB |
| Region | `fra` (Frankfurt) |
| Run command | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |

**Comportamento:**
- Controlla la tabella `jobs` ogni **3 secondi**
- Se un job fallisce, **riprova fino a 3 volte** (con backoff di 30s, 60s)
- Si riavvia ogni **3600 secondi** (1 ora) per prevenire memory leak
- Timeout per singolo job: **120 secondi**

**Job processati:**

| Job | Trigger | Cosa fa |
|-----|---------|---------|
| `AnalyzeGalleryImageJob` | Upload foto nel CMS (Galleria) | Scarica l'immagine da S3, la invia a CompreFace per riconoscimento facciale, salva i risultati nel DB |

**Flusso:**

```
Admin carica foto → Web accoda job nel DB → Worker lo preleva → Chiama CompreFace → Salva risultati
```

---

### 3.3 🗄️ Database MySQL 8.4 — $15.23/mese

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

### 3.4 📦 Spaces S3 + CDN — $5/mese

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

### 3.5 🖥️ Droplet CompreFace — $24/mese

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
                    │              Render SSR (Inertia/Vue)
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
| `APP_LOCALE` | (default) | Lingua predefinita |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `${sito-savino-db.HOSTNAME}` | Host DB (injected da DO) |
| `DB_PORT` | `${sito-savino-db.PORT}` | Porta DB (injected da DO) |
| `DB_DATABASE` | `${sito-savino-db.DATABASE}` | Nome database |
| `DB_USERNAME` | `${sito-savino-db.USERNAME}` | Utente DB |
| `DB_PASSWORD` | 🔒 `${sito-savino-db.PASSWORD}` | Password DB |
| `CACHE_STORE` | `file` | Driver cache (filesystem locale) |
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
| `INERTIA_SSR_ENABLED` | (attivo) | Server-Side Rendering |
| `LOG_CHANNEL` | `stderr` | Log su stderr (visibili in DO dashboard) |
| `LOG_LEVEL` | `warning` | Solo warning ed errori |

### Worker (variabili aggiuntive)

| Variabile | Valore | Descrizione |
|----------|--------|-------------|
| `COMPREFACE_HOST` | `http://157.230.98.6:8000` | URL server CompreFace |
| `COMPREFACE_KEY` | 🔒 secret | API key CompreFace |

---

## 6. Build e Deploy

### Pipeline di deploy

```
git push main → GitHub webhook → DO App Platform → Build Web + Worker → Deploy → ACTIVE
```

### Build Web

```bash
composer install --optimize-autoloader --no-dev && npm install && npm run build
```

1. **Composer**: installa dipendenze PHP (autoloader ottimizzato, no dev-dependencies)
2. **NPM install**: installa dipendenze frontend (Vue, Vite, Tailwind...)
3. **NPM build**: compila assets + SSR bundle (`bootstrap/ssr/ssr.mjs`)

### Build Worker

```bash
composer install --optimize-autoloader --no-dev
```

Solo dipendenze PHP (il worker non serve frontend).

### Avvio Web (`start.sh`)

| Step | Comando | Scopo |
|------|---------|-------|
| 1/6 | `migrate --force` | Applica nuove migrazioni DB |
| 2/6 | `storage:link` | Symlink `public/storage → storage/app/public` |
| 3/6 | `cache:clear` | Svuota cache del deploy precedente |
| 4/6 | `config:cache` | Serializza config in file binario (skip `.env` parse) |
| 5/6 | `route:cache` + `view:cache` + `event:cache` | Pre-compila route, Blade templates, event map |
| 6/6 | `filament:optimize` | Cachea componenti, icone Filament |

### Avvio Worker

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Polling sulla tabella `jobs` ogni 3 secondi, max 3 tentativi per job, riavvio ogni ora.

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
| 5 | 📦 Spaces + CDN | 250 GB, fra1 | **$5.00** |
| | | **TOTALE** | **$59.23/mese** |
| | | | **~€54/mese** |
| | | | **~€654/anno** |

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
| Secret in env vars | ✅ Encrypted in app.yaml |
| Trust proxies | ✅ Configurato per App Platform |
| Log level | ✅ `warning` (niente info/debug in prod) |
| OPcache validate_timestamps | ✅ Disabilitato (previene code injection) |

### Task schedulati

| Comando | Frequenza | Scopo |
|---------|-----------|-------|
| `sitemap:generate` | Giornaliero (04:00) | Genera sitemap XML per SEO |
| `activity-log:prune --days=180` | Settimanale | Pulisce log attività > 6 mesi |
| `model:prune` | Giornaliero | Pulisce modelli scaduti |
