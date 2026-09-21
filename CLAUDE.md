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

## 12-bis. Gallery: la cache non si butta, si rigenera

L'archivio fotografico supera le dodicimila foto e costruirlo (query con media,
atlete ed eventi, due indirizzi Spaces per foto) costa una decina di secondi.
`App\Services\GalleryArchive` lo tiene in cache un giorno; a ogni foto, album o
atleta salvati `CacheInvalidationObserver` **non** cancella la chiave
`public:gallery_images:<locale>` ma mette in coda
`RicostruisciLaCacheDellaGallery`, che la sostituisce (unico finché non entra in
lavorazione); lo scheduler lo rilancia ogni ora. Cancellare la chiave farebbe
pagare quei secondi al primo visitatore dopo ogni modifica — anche quelle del
job di riconoscimento dei volti. Le varianti per atleta restano piccole e si
buttano come prima.

## 12-ter. Riconoscimento dei volti (CompreFace)

- Il servizio gira su un droplet a parte, raggiungibile **solo dalla VPC**
  (`COMPREFACE_HOST` in `.do/app.yaml`); la chiave vera sta nel suo Postgres.
  Le foto di addestramento **non restano nel repo né su Spaces**: il pannello
  le manda a CompreFace e le cancella. Per vedere cosa conosce il servizio si
  interroga `GET /api/v1/recognition/faces` dal droplet.
- **`players.ai_face_examples` è una copia, non la verità.** Si incrementa a
  ogni foto appresa ma nessuno lo abbassa quando il servizio viene azzerato o
  ricreato: a settembre 2026 diceva 3-7 esempi per atleta e CompreFace ne
  aveva uno (la foto ufficiale del roster). `volti:riconcilia-contatori` lo
  riallinea ogni notte dallo scheduler.
- **Le foto entrate senza passare dall'upload del pannello non si analizzano
  da sole.** L'import dell'archivio storico ne ha portate undicimila senza
  mandarne una all'AI: `gallery:analyze --pending --limit=600` gira ogni ora e
  le recupera a blocchi, contando anche i job già nella coda `ai`. Un nuovo
  canale d'ingresso delle foto non deve reinventare il dispatch: basta lasciare
  `ai_analyzed_at` nullo.
- CompreFace accetta per l'addestramento **solo foto con un volto**: primi
  piani. Le foto in azione con più persone vengono rifiutate ("More than one
  face"), e usarle ritagliate richiede di aver verificato chi c'è nel ritaglio.
- **Mai esempi con volto piccolo o miniature.** Il 15/09/2026 cinque foto da
  8-32 KB (volto fino a 44 px) di un'atleta arrivata quest'anno hanno
  prodotto 123 tag falsi al 99% su foto della stagione precedente, con i
  titoli SEO riscritti col suo nome. `FacialRecognitionService::addFaceExample`
  ora misura il volto con `/recognize` prima di caricare e rifiuta sotto
  `services.compreface.min_face_px` (90). Una somiglianza alta non prova
  nulla se l'esempio è scadente: confrontare sempre i tag con la stagione
  in cui la persona era in squadra.
- **"Da rivedere" significa "volto grande quasi riconosciuto"**: somiglianza
  fra `review_similarity` (0.97) e la soglia del tag (0.985) su un volto alto
  almeno `review_min_face_px` (80). Prima bastava un volto qualunque senza tag
  e il flag era acceso su tutto l'archivio; a 0.90 prendeva ancora metà delle
  foto, perché due volti qualunque si somigliano spesso al 91-96%. Il job lo scrive in entrambe le
  direzioni: una foto importata già marcata si spegne se l'AI non trova nulla.
- **Il job non toglie mai i tag**: `AnalyzeGalleryImageJob` fa solo
  `updateOrInsert` sul pivot. Per rifare un'analisi sbagliata bisogna prima
  cancellare le righe di `gallery_image_person` con `confidence_score` non
  nullo (quelle manuali lo hanno nullo) e azzerare `ai_analyzed_at`; il
  titolo lo riscrive `optimizeForSeo` anche senza volti riconosciuti.
- I batch `Bus::batch(...)->allowFailures()` dell'analisi non si chiudono mai se
  un job fallisce: `queue:prune-batches` li pota dopo tre giorni.

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
  Anche l'intestazione sopra l'indirizzo ("Sede legale", "Sede amministrativa")
  è un'impostazione tradotta, `contact.address_label`: le chiavi nuove di un
  gruppo si creano con una migrazione, perché `SiteSetting::set()` le mette nel
  gruppo predefinito e non arriverebbero al frontend.
- **La mappa del Palazzetto** (`content_data.maps_iframe_src`) accetta il
  codice `<iframe>` intero di Google: il pannello tiene solo il `src`. Vuota o
  non Google, la pagina centra la mappa su nome e indirizzo della struttura.
- **Il calendario si apre già filtrato sul Savino con `?squadra=savino`**
  (`/stagione/risultati?squadra=savino`, anche via `/risultati`, che passa la
  query): è il link della CTA "Prossima partita" in homepage.
- **Il profilo Instagram delle atlete** (`players.instagram_handle`) si salva
  come nome utente anche se si incolla il link: `Player::instagramUrl()` lo
  espone nella scheda palmarès di `/stagione`.
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
- **I media delle notizie non stanno piu' sul vecchio sito.** Ventinove
  comunicati importati da WordPress citavano immagini, calendari in PDF e
  cartelle stampa in ODT su `savinodelbenevolley.it/wp-content/uploads/`:
  funzionavano solo finche' quel dominio puntava al sito precedente, e il
  giorno della migrazione si sarebbero spenti senza possibilita' di recupero
  (staccato il vecchio sito, i file non sono piu' interrogabili).
  `php artisan news:importa-i-media-dal-vecchio-sito` li copia sotto
  `news/<anno>/<mese>/` sul disco configurato e riscrive i link; `--prova`
  mostra cosa farebbe. E' idempotente e va lanciato **dalla console dell'app**,
  dove vivono le chiavi di Spaces e il database. I ritagli di `srcset` non si
  copiano: `useSanitize` non ammette quell'attributo, quindi il browser non li
  ha mai usati, e il comando li toglie invece di portarsi dietro un centinaio
  di indirizzi morti.

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
- **`content_data` si salva dal form deidratato, mai dallo stato grezzo di
  Livewire.** Un Repeater nello stato grezzo è una mappa `{uuid: voce}` e un
  FileUpload singolo `{uuid: percorso}`: salvati così, i template (che chiedono
  un elenco con `Array.isArray`) nascondono la sezione intera. È successo a
  piani abbonamento, progetti sociali, valori del vivaio, cartelle stampa e
  documenti. `PreservaContentData` prende i valori da `$form->getState()` e
  passa da `App\Support\ContentData::normalizza()`; i test di
  `PageContentDataTest` verificano `array_is_list`, non solo `assertCount`.
- **Nessun campo del form si chiama `content_data` nudo.** C'era un
  `KeyValue::make('content_data')` per le "altre pagine", nascosto sui modelli
  con form proprio: un campo nascosto non viene deidratato e Filament toglie
  dallo stato tutto ciò che sta sotto il suo percorso, quindi `content_data`
  intero. Era la causa del "salvo e sparisce tutto".
- **Le pagine delle impostazioni passano da `$this->form->fill()`**, non da
  `$this->data = …`: senza idratazione un FileUpload con un percorso in
  archivio manda in 500 la richiesta con cui il browser chiede i file già
  caricati (Documenti Legali non si apriva più).
- **Una sezione, una pagina.** `/youth` e `/ticketing` rimandano a
  `settore-giovanile` e `biglietteria` (come `/sociale` → `volley-4-all`): le
  copie del seeder `youth`, `ticketing` e `sociale` sono state tolte perché la
  redazione modificava una pagina e online vedeva l'altra. Non ricreare pagine
  con lo slug di una sezione.
- **Club Race** (`Public/ClubRace`, slug `club-race` sotto Ticketing):
  regolamento nell'editor della pagina, classifica in `content_data.standings`
  (`club`, `points`) ordinata per punti dal frontend
  (`resources/js/Support/clubRaceStandings.js`).
- **L'ordine delle categorie delle news** è `categories.sort_order` dal
  pannello, servito già ordinato: `collapseCategories.js` non deve riordinare
  per conteggio.
- **`SiteSetting::get()` accetta sia `chiave` sia `gruppo.chiave`**: cerca prima
  fra le chiavi nude, poi — se il nome contiene un punto — dentro il gruppo
  corrispondente. `SiteSetting::get('shop.free_shipping_threshold')` funziona, ed
  è la forma usata in tutto lo shop e nelle aste. La colonna indicizzata resta
  `key`: il gruppo non fa parte della chiave, è la via di ripiego.

- **Biglietteria e Campagna Abbonamenti condividono il template `Public/Ticketing`**
  (form in `App\Filament\Forms\Templates\TicketingTemplateForm`), ma ogni blocco
  compare solo se compilato: spazio in evidenza (`feature_*`: testo, grafica,
  pulsante), vantaggi (`benefits`, elenco di `{text}`), fasi della campagna
  (`phases`: titolo, periodo, descrizione — vuote fino alla campagna di luglio
  2027), Gift Card (`gift_card_*`, grafica 390×390) e listino (`plans`). La
  biglietteria **non ha listino**: i prezzi dei biglietti cambiano di partita in
  partita e stanno su Vivaticket; il messaggio `plans_empty` compare solo se
  scritto. La normalizzazione dei blocchi sta in `ticketingBlocks.js`, quella dei
  piani in `ticketingPlans.js`, entrambe con test.
- **Convenzioni** ha il template `Public/Convenzioni`: partner in
  `content_data.partners` (`name`, `url`, `discount`, `description`,
  `how_to_use`, `logo`), introduzione nell'editor. Il logo passa da `CmsFile`
  come ogni altro upload dentro `content_data`.
- **Una chiave di `content_data`, un tipo solo.** I modelli di pagina
  condividono lo spazio dei nomi e Filament idrata anche i campi delle sezioni
  nascoste: un testo salvato sotto il nome di un elenco arriva comunque al
  Repeater dell'altro modello e la pagina va in 500 prima di disegnarsi
  (`foreach() argument must be of type array|object, string given`). È successo
  il 17/09/2026 con `partners` — nota del Talent Day, elenco delle Convenzioni
  — e la nota è passata a `partners_note`. Le chiavi che sono elenchi stanno in
  `ContentData::CHIAVI_ELENCO` (un test le confronta con i Repeater dichiarati
  nei form) e `EditPage::mutateFormDataBeforeFill` tiene fuori dal modulo i
  valori di tipo sbagliato senza cancellarli dall'archivio.
- **Progetto Affiliazioni** ha il template `Public/Affiliazioni`: le società in
  `content_data.affiliates` (`name`, `tier`, `url`, `logo`), raggruppate dal
  frontend nell'ordine di `App\Enums\AffiliateTier` (Main Partner, Partner
  Ufficiale, Società Affiliate) con le intestazioni tradotte in `affiliazioni.*`;
  il racconto resta nell'editor. `php artisan affiliazioni:importa-dal-vecchio-sito`
  rilegge società, livelli, link e loghi dalla pagina del sito precedente ed è
  idempotente (chiave: il nome); i loghi vanno sul disco dei campi di upload del
  pannello, non su quello predefinito. La migrazione che accende il template
  toglie dal racconto le sezioni "Main Partner" e "Partner Ufficiali" scritte a
  testo: sono le stesse società che l'elenco pubblica con il logo, e lasciarle
  le mostrava due volte nella stessa pagina.
- **Le squadre del vivaio si scelgono per categoria**, non per slug:
  `teams.category` (`B1`, `U17`, `U15`) lega le pagine `/stagione/b1`,
  `/stagione/u17` e `/stagione/u15` (voci di menu `/youth/u17`, `/youth/u15`) al
  pannello delle Atlete Youth e al suo filtro. Il nome della squadra cambia con
  il campionato ed è la redazione a scriverlo; l'etichetta della pagina è quella
  della voce di menu.
- **Le lingue diverse da quella di partenza arrivano al form grezze.** Il plugin
  translatable idrata solo la lingua iniziale e monta le altre così come stanno
  in archivio, sia cambiando lingua sia salvando: un FileUpload con il percorso
  nudo al posto della mappa `{uuid: percorso}` mostrava un campo file grezzo in
  inglese, andava in errore al primo PDF e faceva saltare anche il salvataggio
  in italiano sulla validazione dell'inglese (Safeguarding). `EditPage` tiene
  traccia delle `lingueGrezze` e le fa passare da `$form->fill()` prima di
  usarle; per le altre lingue `PreservaContentData` chiama
  `callBeforeStateDehydrated()` così i file caricati lì finiscono su disco.
  Test in `SafeguardingDocumentiTest`.

- **Gli elenchi vuoti in inglese prendono quelli italiani.** `content_data` è
  tradotto in blocco: classifica della Club Race, listino, partner delle
  convenzioni, PDF e cartelle stampa andrebbero ricopiati a mano in ogni lingua,
  e nessuno lo fa — `/en` diceva "classifica non disponibile" e "offerte in
  arrivo". `ContentData::conGliElenchiDiRipiego()` (chiamato da
  `Page::datiPerIlFrontend()`) riempie le sole `CHIAVI_ELENCO` rimaste
  vuote; un elenco compilato in inglese vince sempre. I testi non hanno ripiego.
- **Gli elenchi che non hanno niente da tradurre si salvano in tutte le
  lingue.** Una società affiliata è nome, livello, sito e logo; la classifica
  della Club Race è club e punti: le stesse cose in italiano e in inglese, ma
  `content_data` è tradotto in blocco e ogni lingua ne teneva una copia. Chi le
  modificava con il pannello in un'altra lingua — la lingua resta quella
  dell'ultima pagina su cui si è lavorato — non vedeva cambiare niente sul sito
  italiano e non riceveva nessun messaggio (21/09/2026: tre salvataggi, tutti
  nella sola scheda inglese). Le chiavi stanno in `ContentData::CHIAVI_COMUNI` e
  le riscrive `EditPage::allineaLeChiaviComuni`, solo per quelle che il modulo
  ha mostrato. Restano fuori `press_kits`, `partners`, `magazines` e
  `team_photos`, che hanno titoli e descrizioni. Test in
  `ChiaviComuniAlleLingueTest`.
- **La pagina arriva al frontend da `Page::datiPerIlFrontend()`**, una porta
  sola: ripiego sulla lingua di partenza, percorsi dei file caricati dal
  pannello (`CmsFile`) e filtro del video di coda (`LiveStream`). Sponsor,
  Contatti e Gallery hanno una rotta propria in `PublicController` e
  `GalleryController` e passavano il record grezzo: sulla pagina Sponsor
  inglese si leggevano le etichette dei numeri d'impatto senza i numeri. Una
  rotta nuova che pubblica una `Page` passa di lì. Test in
  `PaginePubblicheConRottaPropriaTest`.
- **Il testo dell'editor è l'introduzione e sta sotto l'hero** nei modelli
  Ticketing e Comunicazione (come già in Sociale, Convenzioni e Club Race): in
  fondo alla pagina "compila il modulo almeno 48 ore prima" si leggeva dopo il
  modulo. Non deve cominciare con un `<h2>` uguale al titolo: l'hero lo mostra già.
- **Un riquadro che la redazione non può togliere viene riempito a caso.** Il
  riquadro "Al botteghino" degli abbonamenti era diventato un secondo
  "Vantaggi". Ora i due riquadri di "Informazioni sull'acquisto" compaiono solo
  se compilati, e la Missione del modello Sociale pure: sezioni nuove nascono
  facoltative.
- **Gli indirizzi scritti dalla redazione passano da `safeUrl`**, che completa
  un'email nuda in `mailto:` e un `www.` in `https://`: senza schema il browser
  li legge come percorsi relativi (il "Contattaci" di Hospitality portava a
  `/sponsor/marketing@…`, 404).
- **Il pulsante d'iscrizione del Talent Day segue le tappe**: con tutte le
  tappe `sold_out` (esaurite o concluse) il modulo sparisce e compare
  `talent_day.signup_closed`; torna da solo alla prima tappa aperta. Senza tappe
  in elenco decide la redazione, come prima.
- **I documenti del Safeguarding possono puntare ai PDF dei Documenti Legali**
  (`legal/…`): Filament non cancella dal disco un file tolto da un upload
  (nessun `deleteUploadedFileUsing` nel progetto), quindi toglierlo dalla pagina
  non rompe il link del footer. Se un giorno si attiva la cancellazione dei
  file, quei due percorsi condivisi vanno prima duplicati.
- **I valori senza lingua ripiegano sull'italiano come gli elenchi**: le chiavi
  di `content_data` che finiscono in `_url`, `_image`, `_link`, `_email`, `_src`,
  `_value`, `_file` non hanno traduzione, e in inglese restavano vuote (la
  Biglietteria senza link a Vivaticket, Hospitality senza pulsante, Sponsor
  senza numeri). Vale la stessa regola: un valore compilato in inglese vince.
- **Un indirizzo che non esiste deve rispondere 404.** `/stagione/atleta/{slug}`
  con uno slug inventato serviva la pagina della stagione con stato 200, e la
  pagina CMS `home` disegnava una home svuotata sul proprio slug (ora 301 su
  `/`). Vale anche per `/summer-camp/summer-camp`.
- **La sitemap pubblica l'indirizzo di sezione**, non `/{slug}`:
  `PageController::percorsoPubblico()` è l'unica fonte di verità, e le pagine
  contenitore (home, societa, sponsor, shop, comunicazione) restano fuori. Prima
  24 indirizzi su 38 erano redirect.
- **La soglia della spedizione gratuita è quella delle zone di spedizione.** Il
  carrello leggeva `shop.free_shipping_threshold` (che in archivio non esiste)
  e ripiegava su 50 €, mentre il checkout applica il `free_threshold` della zona
  (100 € per l'Italia): il cliente vedeva "spedizione gratuita sbloccata" e poi
  la pagava. `CartController::sogliaDellaSpedizioneGratuita()` legge
  l'impostazione se c'è, altrimenti la zona dell'Italia.
- **Gli indirizzi che il pannello raccoglie non si mettono in `<Link>`.** Un
  `<Link>` di Inertia verso un sito esterno (Vivaticket nelle CTA della
  homepage) parte come XHR e non porta da nessuna parte: si usa
  `isExternalLink()` / `externalLinkAttrs()` di `resources/js/Support/menuLinks.js`.
  I link interni scritti a mano (`href="/news"`) perdono il prefisso della
  lingua: vanno scritti con `route()`.
- **La voce di menu "Foto Ufficiale" esce dal menu finché il PDF non c'è**
  (`MenuItem::fotoUfficialeMancante()`), come i documenti legali mancanti:
  portava a un messaggio d'errore. Ricompare salvando l'impostazione.
- **Squadra creata dal pannello = squadra della società** (`is_internal`, §12):
  le avversarie arrivano solo dal sync.
- **Un campo `hidden()` non viene deidratato** (vale anche per `Select`): per un
  valore fisso serve `Forms\Components\Hidden`. Con una `Select::hidden()` la
  creazione di un membro dell'organigramma falliva sul NOT NULL di `type`.
- **Un modulo che mostra una sola chiave di una colonna JSON la riscrive tutta**:
  l'azione di modifica di Messaggi e Accrediti salvava `extra_data` con la sola
  nota dell'amministratore, cancellando testata, ruolo, gara e telefono. Si fonde
  con `mutateFormDataUsing`.
- **Revisione dei contenuti della redazione**: `activity_logs` dice chi ha
  toccato cosa (`user_id`, `model_type`, `model_id`). Le correzioni ai testi in
  produzione si fanno con una migrazione a guardie (tocca un valore solo se è
  ancora quello sbagliato), provata a secco su una copia in memoria delle righe
  lette in sola lettura: vedi `2026_09_20_100000_revisione_dei_contenuti_della_redazione`.

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

---

## 17. Pagamenti dello shop (Stripe, PayPal, bonifico)

- **Per uscire dal sito verso il gateway serve `Inertia::location()`, mai
  `redirect()->away()`.** Il modulo di checkout è un form Inertia: la POST parte
  come XHR, e un 302 verso stripe.com o paypal.com viene seguito dalla stessa
  XHR, che sbatte contro il CORS del gateway e muore in console — con l'ordine
  già creato e la merce riservata. Vale per il checkout dello shop e per quello
  delle aste. Fuori da Inertia `Inertia::location` degrada da sé al 302.
- **Gli indirizzi di ritorno si chiedono a `Order::successUrl()` /
  `cancelUrl()`**, non si ricompongono a mano: il segmento della rotta si chiama
  `orderToken`, e scritto `['order' => …]` il generatore lancia
  `UrlGenerationException` — la sessione di pagamento non nasceva affatto, né
  con Stripe né con PayPal.
- **PayPal incassa in due punti, ed è voluto.** La cattura avviene al ritorno
  del cliente (`CheckoutController::success`, con il `token` che PayPal mette in
  coda al `return_url`) e nel webhook `CHECKOUT.ORDER.APPROVED`. Il primo che
  arriva registra il pagamento, l'altro si ferma sull'idempotenza della coppia
  (ordine, transazione); un ordine già catturato (`ORDER_ALREADY_CAPTURED`) non
  è un errore, si rilegge. Con la sola strada del webhook, una notifica che non
  arriva significava denaro mai incassato e ordine annullato dopo un'ora.
- **Il numero d'ordine non si legge solo dalla risposta della cattura.** Lo
  schema di PayPal dichiara `custom_id` sull'unità d'acquisto e sulla cattura,
  ma non garantisce che la risposta lo riporti: si guarda anche l'ordine
  contenuto nell'evento e, in ultima istanza, si risolve l'ordine dal
  `reference_id` (il numero d'ordine).
- **Un gateway senza credenziali non si offre** (`PaymentGateway::configurato()`,
  usato dalla pagina di checkout e dalla validazione): mostrarlo significa
  creare l'ordine, riservare la merce e solo allora mandare il cliente
  sull'errore generico. In produzione le chiavi di Stripe non sono impostate:
  finché restano fuori, quel metodo non compare.
- **L'importo incassato si confronta con il totale dell'ordine.** Fra
  l'apertura della sessione e il pagamento il totale può cambiare (il pannello
  ritocca l'ordine, si riprova un pagamento): incassato meno del dovuto, il
  pagamento si registra ma l'ordine NON si conferma e va in revisione manuale;
  incassato di più, l'ordine si conferma e la differenza resta segnalata.
- **Un ordine con `payment_id` valorizzato non si annulla e non si ripaga.**
  `order:check-unpaid` salta gli ordini con una transazione registrata (non
  sono checkout abbandonati: i soldi sono in cassa) e il pulsante "riprova il
  pagamento" li manda alla pagina di conferma invece di aprire una seconda
  sessione.
- **`php artisan paypal:verifica`** dice se le credenziali sono buone, se il
  webhook configurato esiste, se punta a questo sito e se ascolta gli eventi che
  il codice gestisce. Dall'esterno non si distingue un impianto sano da uno
  rotto: l'endpoint risponde 400 sia con la firma sbagliata sia con le
  credenziali sbagliate. Va lanciato dove vivono le variabili (in locale con le
  chiavi sandbox, in produzione dalla console dell'app).

---

## 18. Content Security Policy e header di sicurezza

`App\Http\Middleware\SecurityHeadersMiddleware` serve **due policy diverse**, e
la differenza sta solo in `script-src`:

- **Sito pubblico**: niente `unsafe-inline` né `unsafe-eval`. Gli script in
  linea passano per un **nonce** generato a ogni richiesta
  (`Vite::useCspNonce()`), che `app.blade.php` stampa sui tag propri e passa a
  `@routes(null, $cspNonce)`. **Un nuovo `<script>` in linea nel layout, o un
  gestore scritto dentro un attributo (`onload=`, `onclick=`), non viene
  eseguito**: il nonce non copre gli attributi di evento — era il caso del
  foglio dei font, caricato con `rel=preload` e `onload`, ora un normale
  `rel=stylesheet`. GA4 e il Pixel restano leciti perché si iniettano da soli
  creando un tag con `src` verso i loro host, senza codice in linea.
- **Pannello** (`admin`, `admin/*`, `filament/*`, `livewire/*`): `unsafe-inline`
  e `unsafe-eval` restano, perché Alpine valuta le espressioni dei template con
  `new Function`, e in più sono ammessi `fonts.bunny.net` (il font Outfit di
  Filament). Il pannello **non passa dal gruppo `web`**: il middleware è
  registrato nel suo stack in `AdminPanelProvider`, ed è da lì che arrivano
  anche `X-Frame-Options` e gli altri header, che prima gli mancavano del tutto.

Una pagina servita da `CachePublicResponse` ripete per un minuto il nonce con
cui è stata costruita: quella cache salva l'HTML e le sue intestazioni insieme,
quindi restano coerenti. Separare i due (per esempio rigenerando l'header su un
cache hit) lascerebbe la pagina senza script, in silenzio.

`frame-src` e l'elenco di `LiveStream::embedUrl()` vanno tenuti allineati (§16).
Test in `tests/Feature/SecurityHeadersTest.php`.

---

## 19. Anteprime social (WhatsApp, Facebook, Telegram…)

Il layout `app.blade.php` serve meta `og:` **statici** e non è lì che si
correggono le anteprime: i crawler non arrivano al rendering Inertia. Le
anteprime le costruisce `App\Http\Middleware\ServeSocialCrawlerMeta`, che
intercetta gli user-agent dell'elenco `CRAWLER_PATTERNS` su tutto il gruppo
pubblico (`routes/web.php`) e risponde con un HTML minimale. Le pagine Vue
impostano comunque il proprio `<Head>`, che copre browser e Googlebot.

- **La pagina si riconosce dal nome della rotta, non dal percorso.** Il nome è
  lo stesso in tutte le lingue (`contatti` / `en.contatti`), l'indirizzo no
  (`/contatti` / `/en/contacts`). Con la tabella indicizzata per percorso ogni
  pagina inglese con slug tradotto cadeva sul ripiego generico della home.
  Le pagine del CMS si riconoscono invece dal controller (`PageController@show`),
  perché le sezioni hanno nomi di rotta diversi fra loro e `/summer-camp`
  passa il proprio slug da `defaults()`.
- **Quello che non si sa descrivere passa oltre** (`$next`), e non riceve
  un'anteprima inventata: così il 301 delle sezioni (`/ticketing` →
  `/ticketing/biglietteria`), il 404 di un indirizzo che non esiste e quello di
  una bozza restano tali anche per un crawler. Prima diventavano tutti 200.
- **Gli scope pubblici vanno riapplicati qui**: il route model binding risolve
  qualunque record, quindi bozze e prodotti non pubblicati si filtrano di nuovo
  (`Post::published()`, `Product::shoppable()`). Stessa cosa per le regole che
  vivono nel controller: `/stagione/atleta/{slug}` è solo la prima squadra, e
  `rigaDiRosa()` ricalca la scelta di `PublicController::stagioneForTeam`.
- **I testi delle pagine senza pagina del CMS stanno in `lang/*/site.php`**
  sotto `social`, non nel middleware: il middleware gira prima di `SetLocale`,
  quindi la lingua si passa a mano (`__($chiave, [], $locale)`), e con le
  stringhe cablate nel codice `/en/stagione` annunciava "Stagione".
- `CachePublicResponse` esclude del tutto le richieste dei crawler, in lettura e
  in scrittura: senza, l'HTML ridotto finirebbe servito a tutti gli anonimi.

Test in `tests/Feature/SocialCrawlerMetaTest.php`.

---

## 20. Shop: catalogo, coupon, spedizione

- **Per confrontare due campi di un modulo si usano gli aiutanti di Filament**
  (`->lte('price')`, `->gte('starting_price')`, `->after('start_date')`), mai la
  regola scritta a mano (`->rule('lte:price')`): i dati validati stanno tutti
  sotto `data.`, la regola cerca il campo alla radice, non lo trova e **fallisce
  sempre**. Il prezzo scontato di un prodotto non si e' potuto salvare per
  settimane, e con lui SKU, peso e date dello stesso modulo. Le traduzioni
  italiane di `gt/gte/lt/lte` ora ci sono: senza, l'errore arrivava a schermo
  come `validation.lte.numeric`.
- **Una colonna fuori da `$fillable` non si scrive dal modulo e non protesta.**
  `Auction::status` e' escluso apposta dalla scrittura di massa, e il campo
  "Stato" del pannello finiva nel nulla: l'asta restava in bozza e la pagina
  pubblica, che elenca solo attive/programmate/concluse, non mostrava niente.
  L'unico varco e' `Auction::cambiaStato()`, che verifica la transizione
  (`TRANSIZIONI_AMMESSE`) invece di fidarsi delle opzioni disegnate nel form, e
  le pagine del pannello dicono all'utente quando il cambio viene rifiutato.
- **Il prodotto di un'asta esce dallo shop e ci rientra da solo.** Il tipo
  `auction` lo toglie dalla vetrina; finche' il cambio si faceva solo alla
  creazione, cancellare l'asta lasciava il prodotto invisibile per sempre, con
  la sua pagina in 404. Il ritorno e' legato al ciclo di vita dell'asta
  (`App\Observers\AuctionObserver`, registrato in `AppServiceProvider`) e il
  tipo si deduce dalle varianti. Sul model restano le sole transizioni di
  stato: la classe era arrivata a 21 metodi, oltre la soglia di SonarCloud.
- **La giacenza di un prodotto con varianti e' la somma delle taglie**
  (`Product::availableStock`). La colonna `products.stock` non la aggiorna piu'
  nessuno da quando l'archivio e' arrivato da WooCommerce: nel pannello
  mostrava 56 dove il sito ne contava 19.
- **In vetrina lo sconto si annuncia solo mentre e' in corso.** `sale_price`
  nuda ignora `sale_start`/`sale_end`, mentre carrello e ordine passano da
  `effectivePrice()`: un ribasso programmato si vedeva subito e al pagamento
  tornava il prezzo pieno.
- **Le impostazioni dello Shop hanno un valore di partenza anche quando la
  riga non esiste** (`database/data/impostazioni_shop.php`, letto dal seeder e
  dalla pagina del pannello). In produzione le righe `shop.*` non c'erano: il
  sito usava i predefiniti e funzionava, ma il modulo si apriva con gli
  interruttori spenti e il primo Salva li scriveva davvero — accendendo le
  aste, la redazione ha mandato il negozio in manutenzione. Unica eccezione
  `shop.free_shipping_threshold`, dove vuoto significa "vale la soglia della
  zona di spedizione": proporre un numero lo scriverebbe, e il carrello
  prometterebbe una spedizione gratuita che il checkout non concede.
- **Un coupon puo' valere solo su alcuni prodotti o categorie**
  (`Coupon::valePerIlProdotto`), che valgono in somma; senza nessuno dei due
  vale su tutto. Lo sconto si calcola sulla **sola parte ammessa** del
  carrello, mentre l'ordine minimo guarda la spesa intera: e' una soglia di
  spesa, non un vincolo su cosa si compra. Per questo `applyCoupon` riceve il
  carrello e non il suo totale.
- **Le fasce di peso stanno in una colonna JSON della zona**
  (`shipping_zones.weight_rates`), non in una tabella a parte: le zone attive
  finiscono in cache come semplici attributi e una relazione non
  sopravviverebbe a quel giro. Si riordinano in lettura, perche' le compila la
  redazione; l'ultima puo' restare senza limite. La soglia della spedizione
  gratuita viene prima delle fasce. Il peso del carrello somma i pesi di
  scheda, e i prodotti che non ne hanno uno valgono
  `shop.default_item_weight_kg` invece di zero, o un ordine intero di articoli
  senza peso viaggerebbe nella fascia piu' economica. **Il conto e' scritto due
  volte**, in `ShippingZone::calculateShippingCost` e in `Checkout.vue`: se
  cambia una regola vanno cambiate entrambe.
- **Gli articoli collegati sono a senso unico e non passano dalla cache.**
  Mettere B sotto A non mette A sotto B (e' il cross-selling di WooCommerce da
  cui arriva la richiesta). La cache di mezz'ora resta solo sul ripiego
  automatico — quattro prodotti a caso della stessa categoria — perche' chi
  collega un articolo nel pannello deve vederlo comparire subito.
- **La guida alle taglie si sceglie sul prodotto** fra i PDF caricati in
  "Guida Taglie & Contatti" (`App\Support\GuidaTaglie`): uno in particolare, la
  pagina generale con tutti, oppure nessuna e la voce sparisce — serve per le
  maglie vecchie, per cui una guida aggiornata non esiste. Senza documenti
  caricati la voce non compare a nessuno. La tabella delle misure cablata nel
  componente Vue non c'e' piu': quello che si legge online deve esistere nel
  pannello.
