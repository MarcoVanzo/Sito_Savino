# Regole di Progetto — Savino Del Bene Volley

## Database

- **Produzione (DigitalOcean)**: MySQL 8.4 LTS managed (`sito-savino-db`). MySQL 9.x NON è disponibile su DigitalOcean managed databases.
- **Locale (sviluppo)**: MySQL 9.6.0 (Homebrew, porta 3306). Retrocompatibile con MySQL 8.4.
- **Test (PHPUnit)**: MySQL con database dedicato `sito_savino_test` (stesse credenziali del dev). Allineato a produzione per evitare incompatibilità SQL.
- **NON usare mai funzionalità specifiche di MySQL 9.x** che non siano disponibili in MySQL 8.4, altrimenti il codice si rompe in produzione.
- Il `docker-compose.yml` contiene un container MySQL 8.4 sulla porta 3307 (alternativa al MySQL locale). Usare solo se il MySQL Homebrew non è disponibile.
- Redis è usato per sessioni, cache e code (sia locale che produzione usa `database` driver per sessioni/cache/code su DigitalOcean).

## Deployment

- Hosting: **DigitalOcean App Platform**
- Config deploy: `.do/app.yaml`
- Branch di produzione: `main`
- Storage file: **DigitalOcean Spaces (S3-compatible)**, regione `fra1`
- SSR attivo con `bootstrap/ssr/ssr.mjs`
