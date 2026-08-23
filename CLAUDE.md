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
  `savino-red` (#DF338F), `savino-fucsia` (#F8269C), `savino-pink` (#ED028C).
  Il token `savino-gold` (#C9A84C) non esiste più: l'oro era fuori dalla palette
  della Brand & Digital Style Guide 2026-2027 e ogni suo uso è passato al fucsia
  ufficiale. Restano d'oro solo le serie dei grafici del pannello, dove il colore
  distingue un dato e non veste il brand.
- **Loghi centralizzati** in `resources/js/Constants/logos.js`. NON usare percorsi
  hardcoded — importare sempre le costanti `LOGOS`.
- **Loghi web** in `public/images/`: `logo.png` (volley a colori),
  `logo-volley-white.png` (volley bianco), `logo-corporate.png` (corporate con payoff),
  `logo-corporate-left.svg` / `logo-corporate-left-white.svg` (corporate con cubo a
  sinistra, usati in header e footer), `logo-corporate-name.svg` /
  `logo-corporate-name-white.svg` (senza payoff), `logo-corporate-icon.png` (solo cubo),
  `logo-lvf.png` (LVF ufficiale), `logo-lvf-small.png` (LVF ridotto).
- **Il marchio corporate va servito vettoriale, mai raster.** Il cubo sono 93 righe
  sottili: un PNG da 3110 px disegnato a 200 px viene ridotto di quasi otto volte, le
  righe finiscono sotto il passo dei pixel e si spezzano in puntini. Gli SVG sono
  estratti dai PDF ufficiali (`Savino Del Bene Digital Logo - Payoff - RGB.pdf` e
  pag. 11 di `Branding_Guidelines.pdf`), non ridisegnati.
- **Sotto i 40 mm il payoff si toglie.** Il brand book della Spa (§1.2, pag. 14) vieta la
  versione con payoff sotto i 40 mm, cioè 151 px CSS: sotto quella misura va
  `logo-corporate-name*.svg`. In testata il marchio va da 160 a 238 px, quindi resta
  sempre la versione con payoff; la variante senza resta disponibile per usi più
  piccoli. Il margine sul minimo è però sottile: la larghezza discende da quella del
  logo volley (`--corporate-logo-w` = `--volley-logo-h` × 1,9), quindi rimpicciolire
  il volley porta il marchio sotto i 151 px e obbliga alla variante senza payoff.
- **La testata è centrata sul logo volley.** La riga è alta quanto il logo più l'aria
  attorno (`--header-h` = `--volley-logo-h` + 16 px, in `PublicLayout.vue`): il centro
  del volley è il centro della riga, e quindi quello del menu, del pulsante Shop e
  delle icone. Prima il logo sbordava sotto l'header e il suo centro cadeva 28 px più
  in basso di quello del menu. L'altezza della testata non è più un numero fisso:
  l'hero della home, che risale sotto l'header, legge la stessa `--header-h`.
- **Il marchio della Spa è l'80% dell'ingombro del logo volley** (238 × 52 px contro
  125 × 125 al massimo della scala). Il volley resta il segno principale della
  testata: dimensionare il marchio corporate sull'altezza del volley lo porterebbe a
  455 px di larghezza, mangiando al menu un corpo intero di testo.
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

---

## 14. Contenuti delle pagine CMS (`pages.content_data`)

- **Struttura piatta, sempre.** I template Vue e i form del pannello leggono
  chiavi di primo livello (`hero_label`, `cta_title`, `stat1_value`,
  `press_kit_2_title`…). I dati storici usavano una struttura annidata
  (`hero.badge`, `become_sponsor.stats.0.value`) ed è per questo che il sito
  mostrava testi che in redazione non esistevano: la migrazione
  `2026_08_19_090000_allinea_content_data_alla_struttura_del_pannello` li ha
  convertiti. Non reintrodurre chiavi annidate: aggiungendo un campo, il nome
  nel form, nel Vue e nel file dati deve essere lo stesso.
- **Niente contenuti di esempio cablati nei componenti.** Un fallback nel Vue
  (era il caso della timeline in `Societa/Storia.vue`) produce una pagina che
  la redazione non può modificare perché non trova da nessuna parte quello che
  vede online. I valori iniziali stanno in `database/data/page_content_data.php`
  e `database/data/storia_timeline.php`, usati sia dai seeder sia dalle
  migrazioni; nel componente resta solo lo stato vuoto.
- **Recapiti e dati societari stanno nelle impostazioni**, gruppo `contact`
  (Impostazioni → Contatti): footer, pagina Contatti, Comunicazione e Settore
  Giovanile leggono da lì. La pagina CMS "Contatti" aveva campi con gli stessi
  nomi dentro `content_data`, che nessuno leggeva: sono stati tolti, non vanno
  reintrodotti. Nella pagina restano i testi e la rubrica dei referenti.
- **Le pagine di sezione hanno contenuti propri.** Abbonamenti, biglietteria,
  accrediti stampa, cartelle stampa, progetti sociali, volley 4 all, settore
  giovanile, talent day e organigramma condividono il template con la pagina
  principale ma avevano `content_data` vuoto: online si vedevano i testi di
  ripiego delle traduzioni, nel pannello i campi erano vuoti. I valori iniziali
  stanno in `database/data/page_template_defaults.php`, per template.
- **La meta description viene dalla colonna `meta_description`** della pagina
  (scheda SEO del pannello), non da `content_data`: cinque template la leggevano
  dal posto sbagliato e la descrizione scritta in redazione non arrivava a
  Google.
- **`CmsPagesSeeder` non si rilancia in produzione**: fa `updateOrCreate` su
  tutte le pagine e sovrascriverebbe il lavoro della redazione.
- **Galleria di pagina**: collection media `gallery` sul model `Page`
  (accessor `gallery_images`), non upload su disco — in produzione i file
  stanno su Spaces e un percorso `/storage/...` costruito a mano non risolve.

- **Niente contenuti nel codice dei componenti.** Progetti sociali, valori del
  vivaio, attività e turni del camp, servizi del palazzetto, documenti di
  safeguarding, numeri della homepage, argomenti del modulo contatti, menu e
  canali social del footer erano elencati nei `.vue` come valore di ripiego:
  online si vedevano, in redazione non esistevano. Ora i componenti leggono solo
  ciò che arriva dal backend e nascondono la sezione quando è vuota. Se serve un
  contenuto iniziale si popola con una migrazione, non con un array nel Vue.
- **Le impostazioni di tipo `json` sono tradotte come le altre.**
  `SiteSetting::resolveForLocale()` risolve anche i valori già decodificati:
  senza, i numeri della homepage arrivavano al frontend come
  `{"it": […], "en": […]}` e la sezione restava vuota.

- **La coda multimediale delle pagine** (video + galleria) è un blocco condiviso:
  `PageMediaTail.vue` lato sito, `PageTemplateForms::mediaTailSchema()` lato
  pannello. Il video passa da `LiveStream::embedUrl()` come le dirette delle
  gare: la lista dei domini incorporabili è una sola (§16).
- **I file caricati dentro `content_data`** si dichiarano in
  `App\Support\CmsFile::resolveInContentData()`, che riscrive il percorso in
  indirizzo pubblico. Un campo di upload nuovo che non passa di lì produce un
  link rotto in produzione, dove i file stanno su Spaces e non sotto `/storage`.
- **I Repeater facoltativi vogliono `defaultItems(0)`**: Filament ne apre uno
  già pronto, e se i suoi campi sono obbligatori una pagina nuova non si salva
  finché non lo si compila o lo si cancella.
- **Il materiale stampa è l'elenco `press_kits`**, non quattro caselle fisse:
  servono il logo, il brand book e una cartella per ogni gara.
- **Le richieste di accredito stampa** sono `ContactMessage` con oggetto
  `PressAccreditationController::SUBJECT`. È l'unico legame con l'elenco
  "Richieste Accrediti" del pannello (`PressAccreditationResource`, slug
  `forms`): cambiarlo da una parte sola svuota l'elenco senza errori.
- **Le etichette del menu principale pesano sul layout dell'header.** La barra
  si dimensiona sullo spazio che le resta accanto ai loghi
  (`useHeaderNavFit`): allungare una voce di un paio di parole costa un corpo di
  testo a tutte le altre, e a schermi stretti fa collassare la barra nel pannello
  a scomparsa. Prima di rinominare una voce, misurare — o accorciarne un'altra.
  Vale anche per la voce del pulsante Shop, che è misurata allo stesso modo:
  "Shop Ufficiale" costava un'ottantina di pixel più di "Shop".
- **La voce marcata "Evidenziata" (`menu_items.is_highlight`) esce dalla barra** e
  diventa il pulsante pieno della testata (`ShopCtaButton`), l'unico elemento
  colorato fra voci tutte bianche. Ne va tenuta una sola: il layout prende la
  prima. Sul telefono resta invece una voce del pannello a scomparsa.
- **`SiteSetting::get()` accetta sia `chiave` sia `gruppo.chiave`**: cerca prima
  fra le chiavi nude, poi — se il nome contiene un punto — dentro il gruppo
  corrispondente. `SiteSetting::get('shop.free_shipping_threshold')` funziona, ed
  è la forma usata in tutto lo shop e nelle aste. La colonna indicizzata resta
  `key`: il gruppo non fa parte della chiave, è la via di ripiego.

## 15. Sponsor

- I livelli sono in `App\Enums\SponsorTier` e l'**ordine dei case è l'ordine di
  pubblicazione** nella pagina. `gold`, `silver` e `standard` restano solo per
  i record storici.
- Il raggruppamento è in `App\Services\SponsorDirectory` (cache
  `public:sponsor:tiers:<locale>`, invalidata da `CacheInvalidationObserver`):
  è condiviso fra `/sponsor` e le pagine di sezione, non va duplicato.
- `php artisan sponsors:import-legacy` rilegge sponsor, livelli, link e loghi
  dalla pagina pubblica del sito precedente. È idempotente (chiave: il nome) e
  non tocca gli sponsor inseriti a mano che non compaiono su quella pagina.
  Riconosce come sponsor solo le immagini con `alt`: senza quel filtro
  entravano in elenco il marchio in testata e i pixel di tracciamento.
- Le richieste di sponsorizzazione vanno a `marketing@savinodelbenevolley.it`
  con oggetto precompilato (campi `contact_email` / `contact_subject` della
  pagina Sponsor).

## 16. Diretta streaming delle gare

- Campo `games.stream_url`, inserito dalla redazione; il sync della Lega non lo
  tocca perché scrive solo le colonne `lvf_*`.
- `App\Support\LiveStream::embedUrl()` traduce il link nell'indirizzo da mettere
  in `<iframe>` e **accetta solo YouTube, Vimeo, Twitch e Dailymotion**. Un
  dominio fuori elenco verrebbe caricato dentro la pagina con i permessi del
  sito: in quel caso il frontend apre una scheda nuova invece di incorporarlo.
  Non allargare la lista senza valutare cosa si sta incorporando.
