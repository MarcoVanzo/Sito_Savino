# CLAUDE.md — Progetto "Sito Savino" (Savino Del Bene Volley)

> File di istruzioni permanenti per Claude. Vale per Claude Code, per i connettori MCP
> e per la chat normale. Leggere sempre prima di operare sul database o sul codice.
> Questo file è l'unica fonte di verità delle regole di progetto (unifica il precedente
> `.agents/AGENTS.md`).

---

## 1. Contesto del progetto

- Stack: sito Savino su **Laravel 13** (Filament CMS, Inertia + Vue, SSR) con database
  **MySQL 8.4 gestito su DigitalOcean** (App Platform: servizio web + worker, region `fra1`).
- File chiave: `docker-compose.yml` (ambiente locale), `.do/app.yaml` (spec App Platform),
  `docs/INFRASTRUCTURE.md`, `tailwind.config.js`, questo `CLAUDE.md`.
- Obiettivo tipico delle sessioni: interrogare il DB, analizzare dati, modificare codice.

---

## 2. Regola d'oro sulla connessione al database

La chat "Home" esegue il codice in una **sandbox isolata dalla rete**: NON può risolvere
DNS esterni né aprire connessioni verso porte database (es. 25060). Ogni tentativo di
`mysql`/connessione diretta da lì fallirà con "Temporary failure in name resolution".

**Non provare a connetterti al DB dalla sandbox della chat. Usa uno di questi due canali,
che girano sulla mia macchina e hanno rete piena:**

1. **Claude Code** (preferito per lavoro sul progetto) — esegue nella shell locale reale.
2. **Connettore MCP MySQL** (preferito per query ripetute in sola lettura).

Se ti viene chiesta una query e sei nella chat sandbox, NON dire "non è possibile":
ricorda all'utente di eseguirla via Claude Code o via il connettore MCP configurato sotto.

---

## 3. Parametri di connessione

### Produzione — MySQL gestito DigitalOcean

```
CLUSTER   = sito-savino-db          # region fra1
PORT      = 25060
SSL       = obbligatorio (certificato CA di DigitalOcean)
HOST      = <dal pannello DO>       # NON è nel repo
DATABASE  = <dal pannello DO>       # default DO tipico: defaultdb
USER      = readonly_savino         # utente DEDICATO in sola lettura (da creare, §5)
```

> ⚠️ HOST, DATABASE, USERNAME e PASSWORD di produzione **non sono nel repository**:
> DigitalOcean li inietta a runtime (`${sito-savino-db.HOSTNAME}` ecc. in `.do/app.yaml`).
> Si recuperano da **DO → Databases → `sito-savino-db` → Connection Details**.
> **Non hardcodarli qui**: il repository è pubblico. Tenerli in un file locale non
> tracciato o come variabili d'ambiente del connettore MCP.
> L'utente admin del cluster è `doadmin` — **non usarlo** per le query (vedi §5).

### Sviluppo locale

```
MySQL Homebrew   : host 127.0.0.1  porta 3306
Docker (alt.)    : host 127.0.0.1  porta 3307   # container MySQL 8.4 di docker-compose.yml
DATABASE = sito_savino     USER = sito_savino     PASSWORD = secret
Test PHPUnit: DATABASE = sito_savino_test
```

Prerequisiti lato DigitalOcean (per connettersi alla produzione):
- L'IP pubblico della macchina che si connette deve essere nelle **Trusted Sources** del DB.
- Scaricare il **certificato CA** dal pannello DO e usarlo (`--ssl-ca=ca-certificate.crt`
  da terminale, oppure `MYSQL_SSL=true` nel connettore MCP).
- Creare l'utente con permesso minimo, solo sul database di produzione (sostituire il nome
  reso da Connection Details, tipicamente `defaultdb`):
  `GRANT SELECT ON defaultdb.* TO 'readonly_savino'@'%';`

---

## 4. Configurazione MCP (esempio)

Aggiungere al file di configurazione MCP (via `claude mcp add` o config del desktop).
Verificare nome pacchetto/variabili sul repo del server MCP scelto.

```json
{
  "mcpServers": {
    "mysql-savino": {
      "command": "npx",
      "args": ["-y", "@benborla29/mcp-server-mysql"],
      "env": {
        "MYSQL_HOST": "<host da DO Connection Details>",
        "MYSQL_PORT": "25060",
        "MYSQL_USER": "readonly_savino",
        "MYSQL_PASS": "***USARE_VARIABILE_AMBIENTE***",
        "MYSQL_DB": "defaultdb",
        "MYSQL_SSL": "true"
      }
    }
  }
}
```

---

## 5. Regole di sicurezza (vincolanti)

- **Sola lettura di default.** Usare l'utente `readonly_savino` con soli permessi `SELECT`.
  Nessuna `INSERT/UPDATE/DELETE/DROP` senza richiesta esplicita e conferma dell'utente.
- **Mai credenziali in chiaro** nei file di config o nel codice: solo variabili d'ambiente.
- **Mai** usare l'utente amministrativo del DB (`doadmin`) per le query.
- Prima di eseguire una query di scrittura, spiegarla e chiederne conferma.
- Per modifiche multiple correlate, usare una transazione.

---

## 6. Protocollo di test (eseguire SEMPRE prima di lavorare)

1. Test di connessione minimo: `SELECT 1+1;` — se passa, l'accesso funziona.
2. Test schema: elencare le tabelle e verificare quella su cui si lavora.
3. Solo dopo questi due, procedere con le query reali.
4. Al termine di un lavoro di codice/query, ri-testare il risultato (es. rilanciare la
   query e mostrare l'output, o eseguire i test del progetto) prima di dichiararlo finito.

---

## 7. Convenzioni per tenere le chat compatte ed efficienti

- Non ripetere il contesto già scritto qui: darlo per acquisito.
- Rispondere in modo diretto; evitare ri-spiegazioni di cose già stabilite.
- Quando una chat diventa lunga, produrre un breve riepilogo dei punti fermi
  (decisioni prese, query validate, stato del lavoro) da riportare nella chat nuova,
  senza perdere i dettagli determinanti.
- Aggiornare questo `CLAUDE.md` quando emergono nuove regole stabili
  (nuove tabelle importanti, nuovi vincoli, nuove credenziali di servizio).

---

## 8. Note operative rapide

- Ambiente locale: `docker-compose.yml` (avviare con `docker compose up -d`).
- DB produzione: solo via Claude Code / MCP, mai dalla sandbox della chat.
- In caso di errore di rete nella chat: NON è un problema di password/utente/SSL —
  è il blocco di rete della sandbox. Passare a Claude Code o al connettore MCP.

---

## 9. Database — versioni e vincoli

- **Produzione (DigitalOcean)**: MySQL 8.4 LTS managed (`sito-savino-db`).
  MySQL 9.x **NON** è disponibile su DigitalOcean managed databases.
- **Locale (sviluppo)**: MySQL 9.6.0 (Homebrew, porta 3306). Retrocompatibile con 8.4.
- **Test (PHPUnit)**: database dedicato `sito_savino_test` (stesse credenziali del dev),
  allineato a produzione per evitare incompatibilità SQL.
- **NON usare mai funzionalità specifiche di MySQL 9.x** non disponibili in 8.4:
  in produzione il codice si romperebbe.
- Il `docker-compose.yml` contiene un container MySQL 8.4 sulla porta 3307 (alternativa
  al MySQL locale). Usare solo se il MySQL Homebrew non è disponibile.
- Redis è usato per sessioni, cache e code in locale; in produzione DigitalOcean il driver
  di sessioni/cache/code è `database`.

---

## 10. Deployment

- Hosting: **DigitalOcean App Platform**; config deploy in `.do/app.yaml`.
- Branch di produzione: `main` (deploy automatico su push, gated dai test CI).
- Storage file: **DigitalOcean Spaces (S3-compatible)**, regione `fra1`.
- SSR attivo con `bootstrap/ssr/ssr.mjs`.

---

## 11. Brand e Loghi

- **Documentazione completa**: `docs/BRAND_GUIDELINES.md` per palette colori, varianti
  logo, regole di utilizzo e dimensioni minime.
- **Colori ufficiali** (da `tailwind.config.js`): `savino-blue` (#003063),
  `savino-red` (#DF338F), `savino-gold` (#C9A84C), `savino-pink` (#ED028C).
- **Loghi centralizzati** in `resources/js/Constants/logos.js`. NON usare percorsi
  hardcoded — importare sempre le costanti `LOGOS`.
- **Loghi web** in `public/images/`: `logo.png` (volley a colori),
  `logo-volley-white.png` (volley bianco), `logo-corporate.png` (corporate con payoff),
  `logo-lvf.png` (LVF ufficiale), `logo-lvf-small.png` (LVF ridotto).
- **File sorgente loghi** nella cartella `Loghi/` (root del progetto), suddivisi in
  `Lega/`, `SDB Azienda/`, `SDB Volley/`.
- **Logo LVF stagione 2026/27**: brand book provvisorio in attesa di nuovo Title Sponsor.
  Usare la versione con dicitura "SERIE A". NON modificare colori, cornice o lettering LVF.
- **Magenta LVF ufficiale**: `#FF23B0` (da brand book). Il token `savino-pink` in Tailwind
  è `#ED028C` — discrepanza nota da valutare.
- **Font**: Montserrat (sans, primario) e Playfair Display (serif, secondario) — scelte
  progettuali, non imposte dai brand book.
