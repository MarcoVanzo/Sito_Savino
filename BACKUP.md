# 🔒 Sistema di Backup — Sito Savino

Sistema di backup sicuro e protetto da cancellazione, interamente su DigitalOcean.

## Indice

- [Architettura](#architettura)
- [Setup Iniziale](#setup-iniziale)
- [Come Funziona](#come-funziona)
- [Backup Database (Giornaliero)](#backup-database-giornaliero)
- [Backup Media (Settimanale)](#backup-media-settimanale)
- [Restore](#restore)
- [Verifica Integrità](#verifica-integrità)
- [Costi](#costi)
- [Troubleshooting](#troubleshooting)

---

## Architettura

```
┌─────────────────────────────────────────────────────────────┐
│                    DigitalOcean                              │
│                                                             │
│  ┌─────────────┐     ┌────────────────────────────────────┐ │
│  │  Produzione  │     │   Bucket di Backup                 │ │
│  │             │     │   sito-savino-backups               │ │
│  │  MySQL DB   │────▶│                                    │ │
│  │  (managed)  │ GPG │   🔒 Versioning ON                 │ │
│  │             │     │   🛡️ Bucket Policy: No Delete       │ │
│  │  DO Spaces  │────▶│   ♻️ Lifecycle: 90gg DB, 30gg Media │ │
│  │  (media)    │copy │                                    │ │
│  └─────────────┘     │   📁 db/YYYY/MM/backup-*.gpg       │ │
│                      │   📁 media/...                      │ │
│                      └────────────────────────────────────┘ │
│                                                             │
│  Orchestrazione: GitHub Actions (schedulato)                │
└─────────────────────────────────────────────────────────────┘
```

### Cosa protegge cosa

| Livello | Protegge da | Limite |
|---------|-------------|--------|
| **Bucket policy** (Spaces) | Cancellazione: nessuna chiave può cancellare dal bucket — verificato sul campo, "delete failed" anche con una chiave fullaccess | Chi ha una chiave con permessi di gestione **può riscrivere la policy**; e `PutObject` resta permesso, quindi un backup si può **sovrascrivere** |
| **Bucket lock** (Cloudflare R2) | Cancellazione *e* sovrascrittura, per la durata della retention, chiunque sia a chiedere | Va configurato una volta sola sul bucket (vedi sotto); finché i segreti `R2_*` non ci sono, la copia non parte |
| **Cifratura GPG** | Lettura: i dump sono illeggibili senza passphrase | Se si perde la passphrase si perdono i backup: sta nei secret GitHub e va tenuta anche altrove |
| **Verifica del restore** | Backup che esistono ma non si ripristinano | Vedi `verifica-restore.yml`: gira ogni lunedì e apre una issue se l'ultimo dump non torna su |

> Su DO Spaces il versioning è dichiarato dallo script di setup, ma DigitalOcean
> non offre né Object Lock né versioning con le garanzie di S3: non dare per
> scontato che una versione precedente esista. La copia davvero immutabile è
> quella su R2.

### La copia fuori sede (Cloudflare R2)

Serve perché tutto il resto vive dentro un solo account DigitalOcean: chi ne
ottiene le chiavi con permessi di gestione arriva anche ai backup. Le credenziali
R2 stanno su Cloudflare, quindi i due perimetri non si toccano.

Si configura una volta sola:

1. Su Cloudflare → R2 → crea il bucket (es. `sito-savino-backup-offsite`), regione
   automatica.
2. Applica la regola di retention — impedisce cancellazione **e** sovrascrittura:
   ```bash
   npx wrangler r2 bucket lock add sito-savino-backup-offsite \
     --name retention-db --prefix db/ --retention-days 90
   npx wrangler r2 bucket lock list sito-savino-backup-offsite
   ```
   Attenzione: un bucket con regole di lock **non si può svuotare** finché le
   regole ci sono. È esattamente lo scopo, ma va saputo prima.
3. Crea un token R2 con permesso di scrittura **solo su quel bucket** e aggiungi
   quattro secret al repository: `R2_ACCOUNT_ID`, `R2_BUCKET`,
   `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`.

Finché i secret non ci sono, `backup-db.yml` salta il passo e lo scrive nel log:
il backup su Spaces continua a funzionare, semplicemente resta in una copia sola.

**Provato sul campo il 18/09/2026**, sul backup vero appena caricato (4,75 MB):

| Tentativo | Esito |
|---|---|
| Rilettura dell'oggetto | riuscita, byte identici all'originale |
| **Sovrascrittura** | **rifiutata**: `The object is locked by the bucket policy` (codice 10069) |
| **Cancellazione** | nessun effetto: l'oggetto è ancora lì, identico |

> Attenzione al secondo caso: `wrangler r2 object delete` stampa **"Delete complete."**
> e restituisce 0 anche quando la regola impedisce la cancellazione. Il file non
> viene toccato — lo si verifica rileggendolo — ma il messaggio dice il
> contrario. Non fidarsi dell'output: controllare che l'oggetto ci sia ancora.

---

## Setup Iniziale

### Prerequisiti

- Account DigitalOcean con accesso all'API
- Repository GitHub con GitHub Actions abilitato
- AWS CLI installato localmente (`brew install awscli`)

### Step 1 — Crea API Key Full-Access (admin)

1. Vai su **DigitalOcean → Settings → API → Spaces Keys**
2. Clicca **Generate New Key**
3. Nome: `backup-admin` (sarà usata solo localmente per il setup)
4. Salva Access Key e Secret in un posto sicuro

### Step 2 — Configura AWS CLI locale

```bash
aws configure --profile do-admin
# AWS Access Key ID:     <backup-admin-access-key>
# AWS Secret Access Key: <backup-admin-secret>
# Default region name:   fra1
# Default output format: json
```

### Step 3 — Esegui lo script di setup

```bash
cd /path/to/sito-savino
./scripts/setup-backup-space.sh
```

Questo script:
- ✅ Crea il bucket `sito-savino-backups`
- ✅ Abilita il versioning
- ✅ Applica la Bucket Policy (Deny Delete)
- ✅ Configura le lifecycle rules
- ✅ Verifica che la protezione anti-cancellazione funzioni

### Step 4 — Crea API Key per il Backup (limitata)

1. Vai su **DigitalOcean → Settings → API → Spaces Keys**
2. Clicca **Generate New Key**
3. Nome: `backup-writer`
4. Salva Access Key e Secret

> ⚠️ Questa key sarà usata da GitHub Actions. Anche se compromessa, la Bucket Policy impedisce la cancellazione dei file.

### Step 5 — Configura GitHub Secrets

Vai su **GitHub → Repository → Settings → Secrets and variables → Actions** e aggiungi:

#### Secrets per il Backup DB

| Secret | Valore |
|--------|--------|
| `BACKUP_DB_HOST` | Hostname MySQL (es. `db-mysql-fra1-xxxxx-do-user-xxxxx-0.b.db.ondigitalocean.com`) |
| `BACKUP_DB_PORT` | `25060` |
| `BACKUP_DB_USER` | `doadmin` (o il tuo utente) |
| `BACKUP_DB_PASSWORD` | Password del database |
| `BACKUP_DB_NAME` | Nome del database |
| `BACKUP_GPG_PASSPHRASE` | Una passphrase lunga e complessa (min 32 caratteri) |
| `DO_BACKUP_SPACES_KEY` | Access Key della key `backup-writer` |
| `DO_BACKUP_SPACES_SECRET` | Secret Key della key `backup-writer` |

#### Secrets per il Backup Media

| Secret | Valore |
|--------|--------|
| `DO_PROD_SPACES_KEY` | Access Key per leggere il bucket di produzione |
| `DO_PROD_SPACES_SECRET` | Secret Key per il bucket di produzione |

### Step 6 — Configura Trusted Sources per il DB (se usi GitHub Actions per il dump)

Se vuoi che GitHub Actions faccia il dump diretto del DB:

1. Vai su **DigitalOcean → Databases → sito-savino-db → Settings → Trusted Sources**
2. Aggiungi gli IP range di GitHub Actions oppure lascia aperto temporaneamente durante il backup

> 💡 Alternativa: se il DB non è raggiungibile da GitHub Actions, considera di creare un Artisan command Laravel che gira sul worker DO.

### Step 7 — Attiva MFA sull'account DO

1. Vai su **DigitalOcean → Account → Security**
2. Abilita **Two-Factor Authentication**
3. Usa un'app authenticator (non SMS)

Questo protegge la Bucket Policy e il bucket stesso dall'accesso non autorizzato al pannello DO.

### Step 8 — Genera la passphrase GPG

Genera una passphrase sicura:

```bash
# Genera una passphrase random di 64 caratteri
openssl rand -base64 48
```

**⚠️ IMPORTANTE**: Salva questa passphrase in un posto sicuro e separato (password manager, cassaforte). Senza di essa, i backup del DB sono irrecuperabili.

---

## Come Funziona

### Backup Database (Giornaliero)

```
MySQL → mysqldump → gzip (-90%) → GPG AES-256 → DO Spaces Backup
                                                  + checksum SHA-256
```

**Schedule**: Ogni giorno alle 03:00 UTC (05:00 CEST)  
**Workflow**: `.github/workflows/backup-db.yml`  
**Path su Spaces**: `db/YYYY/MM/backup-YYYY-MM-DDTHHMMSSZ.sql.gz.gpg`

### Backup Media (Settimanale)

```
DO Spaces Produzione → rclone copy → DO Spaces Backup
                       (solo nuovi/modificati, no delete)
```

**Schedule**: Ogni domenica alle 04:00 UTC (06:00 CEST)  
**Workflow**: `.github/workflows/backup-media.yml`  
**Path su Spaces**: `media/...` (stessa struttura del bucket di produzione)

### Notifiche

In caso di fallimento di un backup, viene creata automaticamente una **GitHub Issue** con label `backup` e `urgente` contenente il link ai log del workflow.

---

## Restore

### Restore Database

#### Opzione 1: Script automatico

```bash
# Lista backup disponibili
./scripts/restore-db.sh

# Restore dell'ultimo backup
./scripts/restore-db.sh --latest

# Restore di un backup specifico
./scripts/restore-db.sh --date 2026-06-15
```

Lo script chiede interattivamente:
- Credenziali del database target
- Passphrase GPG
- Conferma esplicita (`SI-RESTORE`)

#### Opzione 2: Restore manuale

```bash
# 1. Scarica il backup
aws --profile do-admin --endpoint-url https://fra1.digitaloceanspaces.com \
    s3 cp s3://sito-savino-backups/db/2026/06/backup-2026-06-15T030000Z.sql.gz.gpg .

# 2. Decifra
gpg --decrypt --output backup.sql.gz backup-2026-06-15T030000Z.sql.gz.gpg

# 3. Decomprimi
gunzip backup.sql.gz

# 4. Importa
mysql -h <host> -P <port> -u <user> -p <database> < backup.sql
```

### Restore Media

```bash
# Installa rclone se non presente
brew install rclone

# Configura il remote di backup in ~/.config/rclone/rclone.conf
# (vedi il workflow backup-media.yml per la configurazione)

# Copia i media dal backup alla produzione
rclone copy do-backup:sito-savino-backups/media/ do-prod:sito-savino-assets-2026/
```

---

## Verifica Integrità

### Verifica automatica del restore (settimanale)

`verifica-restore.yml` gira ogni lunedì alle 04:30 UTC e fa la sola cosa che
dice davvero se un backup è buono: lo **ripristina**. Scarica l'ultimo dump,
controlla il checksum, lo decifra, verifica che mysqldump l'abbia chiuso, lo
carica in un MySQL di servizio e poi guarda dentro — numero di tabelle, presenza
di quelle senza cui il sito non esiste, righe in `pages`, `players`,
`site_settings`, `menu_items`, e qual è l'ultima migrazione presente. Fallisce
anche se l'ultimo backup ha più di 36 ore, che vuol dire che il giornaliero si è
fermato. Su fallimento apre una issue etichettata `urgente`.

Non tocca la produzione: legge dal bucket e scrive in un container che muore a
fine job. Si lancia anche a mano:

```bash
gh workflow run verifica-restore.yml --repo MarcoVanzo/Sito_Savino --ref main
```

### Verifica del singolo file (manuale)

```bash
# Verifica l'ultimo backup
./scripts/verify-backup.sh

# Verifica un backup specifico
./scripts/verify-backup.sh 2026-06-15
```

Lo script verifica:
- ✅ Checksum SHA-256
- ✅ Decifratura GPG
- ✅ Decompressione gzip
- ✅ Validità del file SQL (header, tabelle, completamento)

### Verifica protezione anti-cancellazione

```bash
# Questo comando DEVE fallire con "Access Denied"
aws --profile do-admin --endpoint-url https://fra1.digitaloceanspaces.com \
    s3 rm s3://sito-savino-backups/db/2026/06/backup-2026-06-15T030000Z.sql.gz.gpg

# Output atteso: delete failed: ... An error occurred (AccessDenied)
```

### Trigger manuale dei workflow

Vai su **GitHub → Actions → Backup Database (o Media) → Run workflow** per eseguire un backup manuale in qualsiasi momento.

---

## Costi

| Elemento | Stima Mensile |
|----------|--------------|
| DO Spaces backup bucket (minimo) | $5.00 |
| Storage aggiuntivo (>250GB) | $0.02/GB |
| Transfer (intra-DC, gratis) | $0.00 |
| GitHub Actions (repo privato) | Gratis (2000 min/mese) |
| **Totale stimato** | **~$5/mese** |

---

## Troubleshooting

### Il workflow fallisce con "Connection refused" sul DB

Il database MySQL managed è dietro firewall. Soluzioni:
1. Aggiungi gli IP di GitHub Actions nelle **Trusted Sources** del DB
2. Oppure crea un Artisan command che gira sul worker DO

### Il workflow fallisce con "Access Denied" su Spaces

Verifica che le API key configurate nei GitHub Secrets siano corrette e attive. Le key scadono se ruotate manualmente dal pannello DO.

### Non riesco a cancellare un file dal bucket di backup

**Questo è il comportamento corretto!** La Bucket Policy impedisce la cancellazione.

Se hai davvero bisogno di cancellare un file (es. per motivi legali):
1. Rimuovi temporaneamente la Bucket Policy dal pannello DO
2. Cancella il file
3. Riapplica la policy con `./scripts/setup-backup-space.sh`

### Ho perso la passphrase GPG

⚠️ **I backup del DB cifrati con quella passphrase sono irrecuperabili.** Per i backup futuri:
1. Genera una nuova passphrase
2. Aggiorna il secret `BACKUP_GPG_PASSPHRASE` su GitHub
3. Salvala in un posto sicuro

### Voglio cambiare la retention dei backup

Modifica le lifecycle rules nello script `setup-backup-space.sh` (valori `NoncurrentDays`) e riesegui lo script.
