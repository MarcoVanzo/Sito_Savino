# CLAUDE.md — Progetto "Sito Savino" (Savino Del Bene Volley)

> File di istruzioni permanenti per Claude. Vale per Claude Code, per i connettori MCP
> e per la chat normale. Leggere sempre prima di operare sul database o sul codice.
> Questo file è l'unica fonte di verità delle regole di progetto (unifica il precedente
> `.agents/AGENTS.md`).

---

## 1. Contesto del progetto

- Stack: sito Savino su **Laravel 13** (Filament CMS, Inertia + Vue, rendering solo
  client-side: **SSR non attivo**) con database
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
- **Colonne translatable di spatie: sempre `text`, mai `varchar` né `json`.** Contengono un
  JSON `{"it":"…","en":"…"}` che sfonda i 255 caratteri (errore MySQL 1406 "Data too long"),
  e con tipo `json` una singola riga legacy in testo semplice fa fallire l'ALTER bloccando
  l'avvio del container (le migrazioni girano a ogni deploy via `start.sh → migrate --force`).
  Il codebase contiene ancora colonne `json` storiche per lo stesso scopo: nuove colonne
  translatable vanno create `text`, e le `json` esistenti si convertono quando si tocca la
  tabella. Le migrazioni allarganti (varchar/json → text) hanno `down()` no-op documentato:
  non sono reversibili in modo sicuro.
- **Lingue del sito**: unica fonte di verità `config('app.supported_locales')` (`['it','en']`).
  Non riscrivere l'array a mano in rotte, observer, provider o middleware.
- **Ricerca del CMS sulle colonne tradotte**: il content driver del plugin
  `filament/spatie-laravel-translatable` è rimpiazzato da
  `App\Filament\Support\TranslatableContentDriver`, agganciato con un `bind()` nel
  container in `AppServiceProvider::register()` (il nome della classe è cablato nel
  trait `HasActiveLocaleSwitcher` del plugin, quindi non c'è un'API di configurazione).
  Quello originale confronta una colonna in `lower()` con il termine non normalizzato
  — qualunque ricerca con una maiuscola dà zero risultati — e manda in errore MySQL
  (3141) appena una riga contiene testo semplice invece del JSON per lingua. Non
  rimuovere il bind senza rifare i test in `tests/Feature/Filament/TranslatableSearchTest.php`.

---

## 10. Deployment

- Hosting: **DigitalOcean App Platform**; config deploy in `.do/app.yaml`.
- Branch di produzione: `main` (deploy automatico su push, gated dai test CI).
- Storage file: **DigitalOcean Spaces (S3-compatible)**, regione `fra1`.
- **SSR non attivo**: `INERTIA_SSR_ENABLED=false` nello spec, non esiste l'entrypoint
  `resources/js/ssr.js` e lo script `build:ssr` non viene mai invocato dalla pipeline.
  Non esiste nessun bundle `bootstrap/ssr/ssr.mjs`. Vedi `docs/INFRASTRUCTURE.md` §6.

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
  `logo-corporate-left.png` / `logo-corporate-left-white.png` (corporate con cubo a
  sinistra, usati in header e footer), `logo-corporate-icon.png` (solo cubo),
  `logo-lvf.png` (LVF ufficiale), `logo-lvf-small.png` (LVF ridotto).
- **File sorgente loghi** nella cartella `Loghi/` (root del progetto), suddivisi in
  `Lega/`, `SDB Azienda/`, `SDB Volley/`.
- **Logo LVF stagione 2026/27**: brand book provvisorio in attesa di nuovo Title Sponsor.
  Usare la versione con dicitura "SERIE A". NON modificare colori, cornice o lettering LVF.
- **Magenta LVF ufficiale**: `#FF23B0` (da brand book). Il token `savino-pink` in Tailwind
  è `#ED028C` — discrepanza nota da valutare.
- **Font**: Montserrat (sans, primario) e Playfair Display (serif, secondario) — scelte
  progettuali, non imposte dai brand book.

---

## 12. Sincronizzazione con la Lega Volley Femminile

Calendario, risultati e classifica **non si inseriscono a mano**: arrivano dal sito
ufficiale della Lega (`legavolleyfemminile.it`), che non espone API. Si scaricano le
pagine pubbliche e si parsano (`robots.txt` consente l'accesso, nessun `Disallow`
sui percorsi usati).

- Comando: `php artisan lvf:sync [--season=2026]`, schedulato ogni ora
  (`routes/console.php`). L'anno è quello di **apertura**: `2026` = stagione 2026/2027.
- Codice in `app/Services/Lvf/`: `LvfClient` (HTTP), `LvfMatchParser` e
  `LvfStandingsParser` (parsing), `LvfSyncService` (upsert). Configurazione in
  `config/services.php` → `services.lvf`.
- Pagine sorgente: `/calendario/` (giornata, fase, impianto), `/risultati/` (set vinti,
  in `th.num`), `/classifica/`.

**Due invarianti da non rompere:**

1. **Idempotenza.** Ogni entità remota ha un identificativo stabile
   (`games.lvf_match_id` = id del Match Center, `teams.lvf_club_id`). Il sync fa upsert
   su quelli: rilanciarlo non duplica nulla.
2. **Il lavoro manuale non si tocca.** Le gare inserite dal CMS non hanno
   `lvf_match_id` e non vengono mai modificate né cancellate.

**Attenzione a `teams`**: contiene sia le squadre della società (`is_internal = true`)
sia gli avversari importati. Una squadra interna senza il flag verrebbe **duplicata** al
primo sync, spezzando il legame con rose e statistiche. Il seeder e la migrazione
`add_lvf_sync_support` impostano il flag.

**La Lega NON usa un identificativo stabile per società: lo rinumera ogni stagione.**
Il Savino Del Bene è `710955` nel 2026/2027 e `710918` nel 2025/2026. Per questo esiste
`team_lvf_club_ids`, che tiene tutti gli identificativi noti di ciascuna squadra:
senza, importare una stagione passata duplicherebbe l'intero campionato. La
risoluzione prova nell'ordine gli alias, gli identificativi della società
(`services.lvf.club_ids`, da tenere aggiornato a ogni stagione) e infine il nome
esatto già in archivio.

**Loghi**: due collezioni media distinte su `Team`. `logo` (LOGO_CUSTOM) è quella del
CMS, `logo-lvf` (LOGO_IMPORTED) quella della sincronizzazione. L'import scrive solo
sulla seconda, quindi non può sovrascrivere una scelta fatta in redazione. Usare sempre
`Team::logoUrl()`, mai `logo_url` direttamente.

La pagina pubblica mostra **l'intero campionato**, non solo le gare della società: i
risultati delle avversarie decidono la classifica e interessano al tifoso. Le gare del
Savino sono marcate `isOwn`, che alimenta sia l'evidenza grafica sia il filtro "Solo
Savino". Le gare sono ordinate **per data** e raggruppate per giornata **e fase**: la
Lega numera le giornate da 1 a 13 sia per l'andata sia per il ritorno, quindi ordinare o
raggruppare per sola giornata accosta la 1ª di ritorno (dicembre) alla 1ª di andata
(ottobre).

I **tabellini** restano invece limitati alle gare della società: sono 26 su 182, e
scaricarli tutti significherebbe centinaia di richieste inutili al sito della Lega.

### Tabellini e statistiche per gara

Le statistiche individuali **esistono e si importano**. Non stanno nella pagina del
Match Center — che le carica in un iframe — ma su un host separato:
`ww5.legavolleyfemminile.it/TabellinoGara_i.asp?IdGara=<lvf_match_id>`, dove `IdGara`
coincide con l'identificativo del Match Center già salvato su `games.lvf_match_id`.

- Import: `App\Services\Lvf\LvfStatsSyncService`, parser `LvfBoxScoreParser`,
  tabella `game_player_stats`. Si scaricano SOLO i tabellini delle gare che coinvolgono
  una squadra interna: 26 su 182, non tutto il campionato.
- Si importano le righe di **entrambe** le squadre, perché servono nella scheda della
  partita. `player_id` è valorizzato solo per le nostre atlete (match sul nome
  normalizzato, insensibile ad accenti e ordine); per le avversarie resta null.
- `player_stats` (storico stagionale) viene **ricostruito** dai tabellini, non
  incrementato: un referto corretto a posteriori non lascia totali gonfiati.
- Attenzione al markup: la pagina è un ASP con tabelle annidate e ogni tabella delle
  giocatrici è avvolta da contenitori che ripetono la stessa intestazione. Il parser
  riconosce quella vera dalla presenza delle sotto-intestazioni (`Tot`, `BP`), non dal
  numero di righe. Sbagliare qui attribuisce le atlete alla squadra avversaria.
- La pagina è servita in **Windows-1252**: `LvfClient::boxScore()` la riconverte.

`sync:legavolley` è un comando diverso e più vecchio: genera dati **simulati** e si
rifiuta di girare in produzione. Non è una fonte reale.

I parser sono coperti da test su fixture HTML reali in `tests/Fixtures/Lvf/`. Se la Lega
cambia il markup, aggiornare le fixture e poi i parser.

---

## 13. Analytics: sito, social, newsletter

Tre pagine del pannello leggono servizi esterni. Documentazione completa in
`docs/ANALYTICS.md`; qui solo i vincoli da non violare.

- **Credenziali fuori dal repository.** Service account Google e segreti Meta
  stanno in `.env` / secret di App Platform. Nel database (SiteSetting) c'è solo
  il Measurement ID di GA4, che il tag espone comunque in chiaro nel browser.
- **Nessuna delle tre pagine deve poter andare in errore per colpa del servizio
  esterno.** `WebAnalyticsService` e `SocialAnalyticsService` non lanciano verso
  la UI: restituiscono un payload con `error` o `degraded`. Una pagina del
  pannello in 500 perché Google è lento è peggio di una che dice "dati non
  disponibili".
- **La serie giornaliera si conserva** (`web_analytics_daily`,
  `social_insights_daily`) e i giorni `is_final` non si richiedono mai più. Per
  Meta questo non è una cache: la Graph API non fornisce lo storico giorno per
  giorno, ogni giornata costa una chiamata. Togliere il controllo su `is_final`
  farebbe crescere il costo senza limite senza cambiare nulla a schermo.
- **Tetto alle chiamate Meta**: 15 aprendo la pagina, 120 nel comando notturno
  (Meta concede circa 200 richieste l'ora).
- **I totali di periodo non sono la somma dei giorni.** Per reach e account
  raggiunti sommare conterebbe più volte la stessa persona: si usa `total_value`
  sull'intervallo intero.
- **`read_insights`**: senza quel permesso la Graph API non dà errore, dà
  metriche vuote. Il rilevamento sta in `FacebookPageInsights` e non va tolto.
- **Il tag GA4 si carica solo dopo il consenso** sui cookie di statistica
  (`resources/js/analytics.js`, Consent Mode v2). Il `page_view` a ogni
  navigazione Inertia è ciò che rende possibile la misura pagina per pagina:
  senza, tutto il traffico finirebbe sulla pagina d'ingresso.
- **Il Pixel di Meta non è la Graph API.** La Graph API legge gli insight dei
  profili social; il Pixel (`resources/js/meta-pixel.js`) misura il sito per le
  inserzioni. `Purchase` è deduplicato per numero d'ordine su `sessionStorage`:
  la pagina di conferma si ricarica da sola in attesa del webhook e senza il
  blocco lo stesso ordine varrebbe una decina di conversioni.
- **Il pixel oggi si carica senza consenso** (`META_PIXEL_REQUIRES_CONSENT`,
  default `false`): è una scelta dichiarata, non una dimenticanza. Il toggle
  "marketing" del banner cookie resta quindi senza effetto finché quella
  variabile non passa a `true`.
