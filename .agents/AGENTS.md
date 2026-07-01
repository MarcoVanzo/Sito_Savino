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

## Brand e Loghi

- **Documentazione completa**: Vedi `docs/BRAND_GUIDELINES.md` per palette colori, varianti logo, regole di utilizzo e dimensioni minime.
- **Colori ufficiali** (da `tailwind.config.js`): `savino-blue` (#003063), `savino-red` (#DF338F), `savino-gold` (#C9A84C), `savino-pink` (#ED028C).
- **Loghi centralizzati** in `resources/js/Constants/logos.js`. NON usare percorsi hardcoded — importare sempre le costanti `LOGOS`.
- **Loghi web** in `public/images/`: `logo.png` (volley a colori), `logo-volley-white.png` (volley bianco), `logo-corporate.png` (corporate con payoff), `logo-lvf.png` (LVF ufficiale), `logo-lvf-small.png` (LVF ridotto).
- **File sorgente loghi** nella cartella `Loghi/` (root del progetto), suddivisi in `Lega/`, `SDB Azienda/`, `SDB Volley/`.
- **Logo LVF stagione 2026/27**: Brand book provvisorio in attesa di nuovo Title Sponsor. Usare versione con dicitura "SERIE A". NON modificare colori, cornice o lettering del logo LVF.
- **Magenta LVF ufficiale**: `#FF23B0` (da brand book). Il token `savino-pink` nel Tailwind è `#ED028C` — c'è una discrepanza nota da valutare.
- **Font**: Montserrat (sans, primario) e Playfair Display (serif, secondario) — scelte progettuali, non imposte dai brand book.
