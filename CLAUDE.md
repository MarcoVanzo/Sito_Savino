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
  tabella. Nello shop sono passate a `text` `products.name` e
  `product_categories.name` (erano `varchar(255)`); restano `json`
  `shipping_zones.name` e `auctions.title` / `description` /
  `charity_description`. Le migrazioni allarganti (varchar/json → text) hanno `down()` no-op documentato:
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
- **Il passaggio del dominio ha una procedura sua: `docs/GO_LIVE.md`.** Il sito va
  online su `savinodelbenevolley.it` il **1 ottobre 2026**; oggi risponde solo su
  `seashell-app-47mmf.ondigitalocean.app`. `php artisan verifica:lancio` dice cosa
  manca (posta, pagamenti, interruttori del negozio, allineamento delle notizie) e
  distingue i blocchi dalle cose da guardare.
- **Nella spec non c'e' nessuna sezione `domains:`, ed e' voluto.** La spec e'
  autorevole: un dominio aggiunto dal pannello DO verrebbe cancellato al primo
  deploy. Ma non va nemmeno messo in anticipo, perche' `APP_URL` vale
  `https://${APP_DOMAIN}`: appena il dominio compare nella spec, ogni indirizzo
  generato fuori da una richiesta (link nelle email in coda, sitemap, feed RSS,
  ritorni dei pagamenti) punta al dominio nuovo mentre li' risponde ancora
  WordPress. Si aggiunge quando si sposta il DNS, non prima.
- **La posta esce dal 25/09/2026, via Resend.** Fino ad allora `MAIL_MAILER`
  non era nella spec e `config/mail.php` cadeva su `log`: conferme d'ordine,
  rimborsi, aste vinte e reset delle password finivano nel log. Le variabili
  (`MAIL_MAILER`, `MAIL_FROM_*`, `RESEND_API_KEY`) stanno a **livello d'app**
  nella spec, così le ereditano web, worker e scheduler. Il DNS del dominio
  resta della Spa (`dns*.sdb.it`, MX Proofpoint, `DMARC p=reject` con SPF
  `-all`): il mittente funziona perche' su Resend il dominio e' verificato con
  DKIM. Se quei record spariscono dal DNS della Spa, le email non vanno in
  spam: vengono **rifiutate**. Storia e record in `docs/GO_LIVE.md` §1.
- **Il webhook di PayPal si modifica, non si ricrea.** L'id in
  `PAYPAL_WEBHOOK_ID` entra nella verifica della firma: creando un webhook nuovo
  per il dominio nuovo si apre una finestra in cui le notifiche arrivano a un
  indirizzo e la firma si verifica contro un altro, e gli ordini restano
  "pending". Cambiando l'URL del webhook esistente l'id resta valido e la spec non
  si tocca.
- **I tre componenti sono tre ambienti distinti, e le variabili vanno ripetute in
  tutti e tre.** Web, worker e scheduler non condividono nulla: una variabile
  scritta solo sotto `services:` non arriva alla coda ne' allo scheduler. Il caso
  peggiore e' `APP_KEY`, perche' senza non si rompe niente a vista — `encrypt()`
  e le firme degli URL smettono semplicemente di combaciare con quelle del web.
  Il 23/09/2026 worker e scheduler avevano un valore cifrato che si decifrava in
  stringa vuota: `social:sync-meta` falliva ogni notte da sempre (il cast
  `encrypted` su `social_accounts.access_token`) senza lasciare traccia, perche'
  `schedule:work` manda l'output in `/dev/null` e Sentry e' spento. Si controlla
  dalla console di ciascun componente:
  `doctl apps console <app> <componente>` e poi
  `php -r 'echo strlen(getenv("APP_KEY"));'`. Il 23/09/2026 quel controllo,
  fatto su tutti e tre invece che sul solo web, ha trovato il caso successivo:
  le credenziali `PAYPAL_*` stavano solo sotto `services:`. Innocue finche'
  nessun job in coda costruisce `PayPalPaymentService` — checkout, webhook e
  pannello sono tutte richieste web — e mute il giorno in cui uno lo fa. Ora le
  tre liste le confronta un test
  (`tests/Unit/VariabiliAllineateFraIComponentiTest.php`): quello che sta sul
  web deve stare anche su worker e scheduler, e le eccezioni — le variabili che
  vivono dentro una richiesta HTTP — sono elencate li' una per una col motivo.

---

## 11. Brand e Loghi

- **Documentazione completa**: `docs/BRAND_GUIDELINES.md` per palette colori, varianti
  logo, regole di utilizzo e dimensioni minime.
- **Colori ufficiali** della Style Guide: blu #003063, rosso #DF338F, fucsia
  #F8269C, rosa #ED028C. **Nei token Tailwind fucsia, rosa e rosso sono
  scuriti** (`savino-fucsia` #D00778, `savino-pink` #D0027B, `savino-red`
  #C91F7A) perché le tinte ufficiali non arrivano al contrasto 4,5:1 delle WCAG
  2.1 AA, che lo shop deve rispettare per l'European Accessibility Act (la
  società fattura circa 10 M€, non è microimpresa): su 25 pagine axe contava
  più di 300 testi illeggibili. Le tinte ufficiali restano come `*-brand` per
  gli usi solo decorativi. **Testo fucsia su fondo blu o grigio scuro** va
  scritto `text-savino-fucsia-chiaro` (#FA5FB6): il fucsia scurito lì non
  basta (3,2:1 su gray-900, 2,5:1 sul blu), e vale anche per le foto, i
  gradienti scuri e gli stati `hover:`. Sulle tinte chiare di fucsia
  (`bg-savino-fucsia/10`, il grigio #e8eaef della home) il fucsia scurito si
  ferma a 4,4:1: lì il testo è `text-[#B8066A]`. Sopra un fondo fucsia il testo
  è bianco, non blu né grigio scuro (2,5 e 3,4:1). Nelle email i pulsanti con
  testo bianco e i testi fucsia usano #D00778, come il token. Il controllo
  è automatico: `scripts/scansione-accessibilita.mjs` (axe-core) gira ogni
  lunedì in `scansione-accessibilita.yml` e fallisce sulle violazioni gravi; il
  lint ha `eslint-plugin-vuejs-accessibility`. **Cosa copre la scansione**: sul
  sito vero solo letture (pagine, scheda prodotto, carrello vuoto, i due
  passaggi del recesso senza conferma); carrello pieno, checkout con gli
  errori, conferma d'ordine e checkout d'asta li percorre il lavoro `flussi`
  sull'app avviata in CI con `ScansioneAccessibilitaSeeder` (`--flussi`
  rifiuta host non locali: mai ordini verso produzione). In `npm test` axe e
  uno screen reader simulato (`@guidepup/virtual-screen-reader`, attrezzi in
  `resources/js/testing/`) sui percorsi critici; la prova umana con
  VoiceOver/NVDA ha il suo protocollo in `docs/ACCESSIBILITA.md`. Il pulsante
  d'ordine non si spegne mai in silenzio: resta attivo e al clic dice cosa
  manca. I PDF generati passano da `PdfAccessibile` (titolo e `/Lang`). La
  dichiarazione di
  accessibilità è la pagina `dichiarazione-di-accessibilita` (testo iniziale in
  `database/data/dichiarazione_accessibilita.php`): i limiti noti elencati lì
  vanno tolti quando si risolvono.
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
- **Magenta LVF ufficiale**: `#FF23B0` (da brand book LVF): è il colore della Lega e
  sta solo nel suo logo, non è un token del sito. Il rosa della Style Guide SDB è
  `savino-pink-brand` (#ED028C).
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
- **Il pixel parte solo col consenso di marketing**
  (`META_PIXEL_REQUIRES_CONSENT`, predefinito `true` in `config/services.php` e
  non sovrascritto nella spec). Questa riga diceva il contrario, con un
  predefinito `false` che nel codice non c'è mai stato: il toggle "marketing"
  del banner ha effetto, ed è ciò che l'informativa dichiara.

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
  di indirizzi morti. Le regole di cosa si copia e dove stanno in
  `App\Services\VecchioSito\MediaDelVecchioSito`, che usa anche l'import dei
  comunicati: sono scritte una volta sola.
- **L'archivio delle notizie si riallinea da `wp-json`, non da un export.** Le
  941 notizie storiche erano entrate da un export statico di WordPress salvato
  sul portatile (`~/wp_export_savino/data`), fermo al 2 luglio 2026 e oggi non
  piu' sul disco; il comando che lo leggeva e' stato tolto dando la migrazione
  per conclusa. Intanto la redazione ha continuato a pubblicare sul vecchio
  sito e il sito nuovo si e' fermato al 26 giugno: tre mesi di comunicati
  mancanti, che dal giorno del feed RSS (§22) sono anche quelli che la Lega non
  riceve. `php artisan news:importa-dal-vecchio-sito` legge invece le API REST
  pubbliche del vecchio sito, quindi e' ripetibile: senza `--da` riparte dalla
  notizia piu' recente in archivio, `--prova` mostra cosa farebbe. Va lanciato
  **dalla console dell'app**, dove vivono database e chiavi di Spaces.
- **Lo scheduler dell'import ha una scadenza, ed e' voluta.** Gira ogni ora
  (`routes/console.php`) perche' fino al passaggio del dominio la redazione
  pubblica sul vecchio sito e la finestra e' di giorni, non di mesi: un
  comunicato uscito in mattinata e lo switch nel pomeriggio starebbero nello
  stesso giorno. Si spegne da solo il 2 ottobre 2026
  (`services.vecchio_sito.leggibile_fino_a`, spostabile se il passaggio slitta):
  dopo, `savinodelbenevolley.it` e' questo sito, `wp-json` risponde 404 e il
  comando fallirebbe a ogni giro. La scadenza e' coperta da
  `tests/Feature/Console/ImportNotizieSchedulatoTest.php`, non solo da un
  commento. Il comando resta lanciabile a mano: l'ultimo giro va fatto subito
  prima di spostare il DNS.
- **La chiave naturale di una notizia e' `wp_id`**, l'identificativo del post su
  WordPress: ce l'hanno tutte le righe dell'archivio, ed e' cio' che rende
  l'import ripetibile. Lo slug e' la seconda strada, per il comunicato che la
  redazione ha gia' scritto a mano: quella riga si riscrive e adotta il `wp_id`,
  invece di prendersi accanto un gemello con lo slug numerato.
- **Le categorie si risolvono per `wp_id`, poi slug, poi nome esatto** — come le
  squadre della Lega (§12) e per lo stesso motivo. Le due strade in fondo non
  sono teoriche: "News Sponsor" da noi si chiama `sponsor` (stesso `wp_id`,
  slug diverso) e "Serie A1 2026/2027" la redazione l'aveva creata a mano come
  `serie-a1-20262027`, senza `wp_id` e prima in ordine di menu. Cercando il solo
  `wp_id` sarebbe nata una seconda categoria con lo stesso nome e nove
  comunicati su quindici sarebbero finiti li' dentro. Trovata per slug o per
  nome, la categoria adotta il `wp_id`: dal giro dopo basta il primo confronto.
- **Le date dei comunicati sono l'ora locale del vecchio sito** (`date` di
  WordPress, non `date_gmt`): e' quella che l'import di allora ha scritto in
  `posts.published_at`, ed e' quella con cui le API confrontano il filtro
  `after`. Il connettore MCP le mostra spostate di due ore — leggerle con
  `CAST(published_at AS CHAR)` prima di concludere che qualcosa si e' spostato.
- **Uno slug come `41541-2` non e' uno slug**: WordPress lo genera da solo
  quando si pubblica senza titolo, ed e' l'id del post. Finirebbe
  nell'indirizzo della notizia e nel `guid` del feed, che non si puo' piu'
  cambiare (§22): l'import lo sostituisce con quello ricavato dal titolo.

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
- **I documenti del Safeguarding rimandano a Documenti Legali per chiave**
  (`documents.*.documento_legale`, risolto da `DocumentiLegali::risolviNeiDocumenti`
  in `Page::datiPerIlFrontend()`), come le voci `documento:<chiave>` del footer:
  sostituendo un PDF in Documenti Legali si aggiornano entrambi. Prima la pagina
  teneva una copia propria del file e restava alla versione vecchia. Il caricamento
  diretto resta per i documenti che non sono di governance.
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
- **Tutto questo vale anche per le aste**: l'ordine del vincitore è un `Order`,
  il vincitore sceglie fra `PaymentGateway::offertiAlleAste()` (quelli dello
  shop meno il bonifico, che non sta nel termine dell'asta) e si incassa sulla
  stessa conferma e con lo stesso webhook. Solo l'annullo torna all'asta
  (`Order::cancelUrl()`), perché il «riprova» dello shop ignorerebbe il termine.
  **Un pagamento arrivato quando l'asta ha già un altro vincitore non conferma
  l'ordine**: si registra e va in revisione da rimborsare
  (`HandlesPaymentWebhooks::astaPassataAdAltri`). Senza, il vecchio vincitore
  che approvava PayPal dopo il termine si riprendeva il lotto già riassegnato,
  perché l'annullo aveva rimesso il pezzo in giacenza. La sessione Stripe di
  un'asta scade col termine del vincitore.
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

**Ogni `throttle:N,M` ha il suo terzo parametro** (`throttle:5,1,shop.checkout.store`).
Senza, la chiave è solo l'IP (o l'utente) e tutti i limiti numerici contano
sullo stesso contatore: cinque errori JavaScript mandati al tunnel di Sentry
bastavano a dare 429 al checkout di un ospite. Lo verifica
`tests/Feature/LimitiDelleRotteSeparatiTest.php`.

**Le pagine servite da `CachePublicResponse` non portano Set-Cookie**, quindi
chi apre il sito direttamente su una di quelle non ha il cookie `XSRF-TOKEN`.
Prima di una richiesta che scrive, `resources/js/bootstrap.js` lo chiede a
`/csrf-cookie` se manca; un 419 o un 429 su una richiesta Inertia torna alla
pagina con un messaggio (`bootstrap/app.php`), con il modulo ancora compilato.

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
  senza peso viaggerebbe nella fascia piu' economica; il peso di un articolo,
  ripiego compreso, lo decide `Product::pesoPerLaSpedizione()`. **Il conto e'
  scritto due volte**, in `ShippingZone::calculateShippingCost` e in
  `resources/js/Support/spedizione.js` (con test): se cambia una regola vanno
  cambiate entrambe. La copia JS e' **una sola** e la usano sia il checkout
  dello shop sia quello dell'asta — quando quest'ultimo aveva la sua, e' rimasto
  indietro alle fasce e mostrava al vincitore una spedizione diversa da quella
  che l'ordine gli addebitava.
- **Gli articoli collegati sono a senso unico e non passano dalla cache.**
  Mettere B sotto A non mette A sotto B (e' il cross-selling di WooCommerce da
  cui arriva la richiesta). La cache di mezz'ora resta solo sul ripiego
  automatico — quattro prodotti a caso della stessa categoria — perche' chi
  collega un articolo nel pannello deve vederlo comparire subito. In cache
  c'e' solo la scelta degli id: prezzo, etichette e giacenza delle card si
  leggono a ogni richiesta. La vetrina (`public:shop`) la buttano anche le
  taglie, i movimenti di magazzino e `prezzi:registra` quando uno sconto
  programmato comincia o finisce: una card che dice IN OFFERTA o ULTIMO
  RIMASTO quando non e' piu' vero e' la pratica ingannevole descritta sotto.
- **La guida alle taglie si sceglie sul prodotto** fra i PDF caricati in
  "Guida Taglie & Contatti" (`App\Support\GuidaTaglie`): uno in particolare, la
  pagina generale con tutti, oppure nessuna e la voce sparisce — serve per le
  maglie vecchie, per cui una guida aggiornata non esiste. Senza documenti
  caricati la voce non compare a nessuno. La tabella delle misure cablata nel
  componente Vue non c'e' piu': quello che si legge online deve esistere nel
  pannello.
- **Le impostazioni scritte vuote dal pannello sono un guasto, non una scelta.**
  Il 21/09/2026 il primo Salva della pagina Shop ha creato in produzione tutte
  le righe `shop.*` e `auctions.*` vuote: `max_qty_per_product` a '' vale zero
  pezzi per prodotto, `active_payment_gateways` a '' non offre alcun metodo di
  pagamento, e i due interruttori sono finiti spenti — negozio in manutenzione.
  Il modulo ora si apre sui valori di partenza (§20, `valoriPredefiniti`), e la
  migrazione `2026_09_22_100000_ripristina_le_impostazioni_dello_shop_rimaste_vuote`
  ha rimesso i numeri **a guardia** (solo se ancora vuoti). Gli interruttori
  restano come li ha lasciati la redazione: riaccendere il negozio lo decide
  lei, non una migrazione.
- **Non creare in una migrazione la forma `gruppo.chiave` di un'impostazione.**
  `SiteSetting::get('shop.enabled')` cerca prima la chiave letterale
  `shop.enabled` e solo dopo la chiave `enabled` nel gruppo `shop`: seminare la
  prima oscura in silenzio un valore salvato nella seconda, sostituendogli un
  valore di partenza. Una chiave che in archivio non c'e' vale il ripiego del
  codice, e il pannello la mostra comunque col suo valore iniziale. Quattro
  test (`SiteSettingTest`, `BidServiceTest`, `ShopCorrectnessAuditTest`) usano
  la forma nuda proprio per questo.

- **Le etichette sulla foto dei prodotti le sceglie la redazione**
  (`products.etichette`, sezione "Etichette in vetrina"): nullo vale
  "automatiche" (NUOVO nei primi 30 giorni, IN OFFERTA durante lo sconto), un
  elenco anche vuoto e' la scelta a mano. Al massimo due, decise da
  `App\Support\EtichetteDelProdotto`. **IN OFFERTA e ULTIMO RIMASTO compaiono
  solo mentre sono vere** (sconto annunciabile, cioè con il prezzo di riferimento dei 30 giorni come il barrato; un pezzo per ogni taglia rimasta),
  qualunque cosa dica il pannello: un'offerta o una scarsita' finte sono
  pratiche ingannevoli (Codice del consumo, artt. 21 e 23).
- **La personalizzazione (la firma della giocatrice) non e' una variante.** E'
  un'aggiunta facoltativa del prodotto (`personalizzazione_nome`, tradotto, e
  `personalizzazione_prezzo`) e un flag sulla riga del carrello: il pezzo in
  magazzino e' uno, quindi giacenza e limite per prodotto si contano sulla
  somma delle righe con e senza firma (`CartService::quantitaPerPezzo`, usato
  anche dal checkout sotto lock). La riga d'ordine ne fotografa nome e
  supplemento. Il prezzo di una riga si compone solo in
  `CartItem::prezzoUnitario()`.
- **Maglie indossate e autografati hanno uno «Stato dell'articolo».** Le
  condizioni li vendono «nello stato descritto nella scheda»: acceso il flag
  `products.usato_o_autografato`, `stato_articolo` (tradotto, `text`) e'
  obbligatorio in italiano. L'obbligo sta in
  `ProductResource::verificaStatoDellArticolo` (beforeSave/beforeCreate), non
  in `->required()`: il plugin translatable rivalida il modulo con i dati di
  ogni lingua visitata e scarta in silenzio quella che non passa, quindi uno
  stato non tradotto avrebbe buttato nome e descrizione inglesi. La riga
  d'ordine lo fotografa in `Order::registraArticolo` (shop e aste), e ordine,
  email, PDF e pannello leggono `order_items.stato_articolo`, mai la scheda.

---

## 21. Cookie, consenso e informative

Cookiebot è stato provato e scartato il 22/09/2026: il prezzo dipende dal numero
di pagine e il sito ne ha 1958 (941 notizie, più altrettante in inglese), cioè
30 €/mese. Consenso, scansione e dichiarazione sono fatti in casa.

- **La scelta del visitatore si legge in un posto solo**: `resources/js/consenso.js`.
  La usano `app.js`, che decide se far partire GA4 e il Pixel, e il banner. Due
  copie della stessa regola divergono, ed è già successo con il calcolo della
  spedizione.
- **Il consenso porta con sé la versione dell'informativa** (`ConsensoCookie::VERSIONE`).
  Alzarla fa ricomparire il banner a tutti: si alza quando cambia *quello che si
  dichiara*, non a ogni ritocco di stile.
- **`consensi_cookie` è la prova del consenso** (GDPR art. 7 §1), non un registro
  statistico: mai l'indirizzo IP in chiaro, solo l'impronta con `APP_KEY` come
  sale, e dodici mesi di conservazione (`consensi:pota`, settimanale).
- **Il Pixel di Meta sta sotto il consenso di marketing**
  (`META_PIXEL_REQUIRES_CONSENT` parte da `true`). La CSP deve tenere
  `www.facebook.com` in `form-action` e `frame-src`, o gli eventi non partono:
  è il pixel, **non** una piattaforma incorporabile, e non va aggiunto
  all'elenco di `LiveStream` (§16).
- **La dichiarazione dei cookie non si scrive a mano.** L'elenco in fondo alla
  Cookie Policy viene da `database/data/cookie_rilevati.json`, che aggiorna il
  workflow `scansione-cookie.yml` (Playwright, lunedì 04:30 UTC), descritto con
  `database/data/catalogo_cookie.json`. Un cookie nuovo va spiegato nel
  catalogo, non nell'editor del pannello: finché non lo è, la pagina lo
  pubblica come "non ancora classificato", ed è voluto.
- **La scansione senza consenso è un controllo, non un inventario**: se prima
  della scelta parte qualcosa che non sia necessario, il workflow diventa rosso.
  Non silenziarlo — è l'unico posto in cui quel difetto si vede.
- **I testi delle informative si correggono con una migrazione a guardie**
  (§14), perché la redazione può averli già riscritti; i contenuti stanno in
  `database/data/informative_privacy.php`, letti da un punto solo
  (`App\Support\TestiDelleInformative`). I dati societari si prendono dalle
  impostazioni, gruppo `contact`: il testo precedente riportava un indirizzo
  che non era la sede.
- **Le `firme` di quel file sono cumulative**: pubblicando una revisione si
  aggiunge la frase della versione che se ne va e non si toglie niente, o una
  migrazione già scritta, rigirando su un database nuovo, non riconoscerebbe
  più niente da correggere.
- **L'informativa non si ricopia altrove.** `PageSeeder` e
  `content_translations_en.php` ne tenevano una versione ferma al 2025 —
  "esclusivamente cookie tecnici", indirizzo sbagliato, un'email che non
  esiste — e ogni ambiente nuovo, compreso il database dei test, nasceva con
  quella. Il seeder ora legge il file dati.
- **Il riconoscimento dei volti è un trattamento biometrico e va dichiarato.**
  Sta nell'informativa dal 23/09/2026 (cosa rileva, cosa conserva, cosa no, e
  che i nomi finiscono nei titoli delle immagini e quindi nei motori di
  ricerca), con la base giuridica dichiarata: **consenso esplicito**
  dell'interessato, art. 9 §2 lett. a. Quel consenso lo raccoglie la società
  fuori dal sito e **nessun campo del pannello lo verifica**: si può addestrare
  qualunque atleta in rosa, U15 e U17 comprese. Alla revoca vanno cancellati il
  soggetto su CompreFace e le righe di `gallery_image_person` con
  `confidence_score` non nullo. Resta il primo punto aperto di
  `docs/PRIVACY.md`.
- **Mappa e video incorporati partono prima del consenso** (`Palazzetto.vue`,
  `LiveStreamModal.vue`, `PageMediaTail.vue`): la Cookie Policy adesso lo dice,
  ma dirlo non lo rende lecito — il rimedio è il click-to-load. È il difetto
  che la scansione settimanale può far diventare rosso: è vero, non è un falso
  positivo.
- **I caratteri tipografici li serve il sito**, non il CDN di Google: i
  `@font-face` stanno in `resources/css/app.css`, i woff2 variabili (Fontsource,
  OFL 1.1, sottoinsiemi latin e latin-ext) in `public/fonts`. Il foglio di
  `fonts.googleapis.com` mandava a Google l'IP di ogni visitatore prima di
  qualsiasi scelta. Il nome della famiglia resta `Montserrat` e non
  `Montserrat Variable`: lo usano `tailwind.config.js`, i modelli delle email e
  la misura delle etichette del menu in `useHeaderNavFit.js`, che con un nome
  sconosciuto misurerebbe il carattere di ripiego (§14).
- **Una conservazione dichiarata senza un comando che la applichi è una frase
  falsa.** I 24 mesi di messaggi e accrediti li faceva rispettare nessuno:
  `contact_messages` non aveva né `prunable` né comando, e restava tutto.
  `messaggi:pota` (settimanale) conta dalla **data del messaggio**, non da
  `updated_at`, che cambia quando la redazione lo segna come letto e
  rimanderebbe in là la scadenza dell'archivio a ogni giro nel pannello.
- **Il titolare del trattamento è la ragione sociale**, non il nome con cui la
  squadra gioca: «Pallavolo Scandicci Savino Del Bene Società Sportiva
  Dilettantistica a Responsabilità Limitata» (CF 94217750481, P. IVA
  06271460484, SDI KRRH6B9, PEC `pallavoloscandicci@legalmail.it`, sede in Via
  Benozzo Gozzoli, 5/6 — 50018 Scandicci). L'informativa diceva «Savino Del
  Bene Volley S.S.D. a r.l.», che non è la denominazione di nessuno: è la
  persona giuridica verso cui si esercitano i diritti, e va per esteso. Il
  copyright del footer usa invece il nome d'uso, ed è giusto così.
- **I diritti si esercitano a `privacy@savinodelbenevolley.it`**, la casella
  indicata anche dall'informativa fornitori e da quella promozionale — non
  `contact.email` (`info@`), che è il recapito generale del sito e resta a
  footer, pagina Contatti e modulo contatti.
- **L'informativa del sito è la pagina, non un PDF.** Il footer chiedeva
  `legalDocs.privacy_policy` e ripiegava sulla pagina solo se il documento
  mancava: i PDF c'erano, quindi da ogni pagina il link apriva l'informativa
  cookie del vecchio WordPress (plugin GDPR Cookie Consent, AddThis, Universal
  Analytics — nessuno dei quali esiste qui) mentre il banner e le caselle dei
  moduli facevano accettare la pagina. **Tutte le voci legali del footer sono
  pagine** (Pagine nel pannello): dal 26/09/2026 anche l'informativa
  promozionale e quella fornitori, che erano PDF e si aprivano in una scheda
  nuova (testi iniziali in `database/data/informative_da_documento.php`). In
  Documenti Legali restano solo i protocolli di governance; l'elenco
  commentato sta in `docs/PRIVACY.md` §1.
- **`docs/PRIVACY.md` è la mappa tecnica**: dove ogni frase dell'informativa
  diventa vera nel codice, quali conservazioni hanno davvero un comando che le
  applica, e i tre punti aperti. Aggiungendo un trattamento si aggiorna quello
  prima dell'informativa.

---

## 22. Feed RSS delle notizie

La Lega Pallavolo Serie A Femminile riprende i comunicati delle società da un
feed RSS: è una richiesta arrivata dalla Lega per il nuovo sito, quindi
l'indirizzo è pubblicato a terzi.

- **L'indirizzo canonico è `/feed`** (`/en/feed` per l'inglese), lo stesso che
  serviva il vecchio sito WordPress: chi lo aveva già registrato non deve
  rifarlo. `/news/feed` e `/rss` sono 301 verso di lui. Cambiarlo significa
  spegnere il feed a chi è abbonato, senza che nessuno se ne accorga: prima si
  avvisa la Lega.
- La rotta `/news/feed` sta **prima** di `/news/{slug}`, o "feed" verrebbe letto
  come lo slug di una notizia.
- **Il prefisso della lingua si chiede al router** (`Route::has('en.news.index')`),
  non si deduce confrontando la lingua con `config('app.locale')`:
  `app()->setLocale()` riscrive proprio quella voce, quindi durante una
  richiesta a `/en/feed` il confronto è sempre vero e il feed inglese
  uscirebbe con gli indirizzi italiani. Stessa regola per il
  `<link rel="alternate">` del layout, che passa da
  `NewsFeedBuilder::indirizzo()`.
- **Il `guid` non è il link.** È `urn:savinodelbenevolley:notizia:<id>` con
  `isPermaLink="false"`, perché è con quello che gli aggregatori riconoscono
  una notizia già letta: usare l'indirizzo significherebbe ripubblicare la
  notizia a tutti gli abbonati ogni volta che la redazione corregge uno slug.
  È anche la forma che il feed di WordPress usa (`?p=<id>`), quindi quella che
  la Lega riceve oggi. **Cambiare il formato del guid ripubblica l'intero
  archivio**: non si tocca.
- **Il contenuto esce dal sito**: gli indirizzi relativi dentro `content`
  diventano assoluti (un `/storage/...` dentro un lettore RSS punterebbe al
  dominio dell'aggregatore), le sequenze `]]>` si spezzano perché non chiudano
  il CDATA a metà comunicato, e i caratteri di controllo dei testi importati da
  WordPress si tolgono: uno solo rende il feed illeggibile a tutti gli
  aggregatori insieme.
- **I campi tradotti si leggono con `testoTradotto()`** (trait
  `App\Models\Traits\TestoTradotto`, su `Post` e `Category`): le righe
  importate da WordPress contengono testo semplice invece del JSON per lingua e
  `getTranslations()` di spatie le restituirebbe come vuote — un titolo che
  sparisce, non un errore che si nota (§9). Non passa da `getTranslations()`
  nemmeno per il caso opposto: quello scarta i valori vuoti, e un campo
  tradotto ma non compilato sembrerebbe una riga storica, pubblicando il JSON
  così com'è.
- **La copertina esce due volte**: `enclosure` con l'originale e la sua
  dimensione esatta, `media:content` con la conversione `detail` da 1200 px
  quando è stata generata — l'originale caricato in redazione pesa qualche mega
  e l'aggregatore lo scaricherebbe per intero.
- Cache di mezz'ora, buttata da `CacheInvalidationObserver` appena la redazione
  salva una notizia o una categoria: un comunicato ritirato deve sparire subito
  anche dal feed. Lo stesso mezz'ora sta nel `ttl` del canale e nel
  `Cache-Control` della risposta.

Test in `tests/Feature/NewsFeedTest.php`.

---

## 23. Gli indirizzi del vecchio sito WordPress

Il dominio `savinodelbenevolley.it` è rimasto su WordPress fino al passaggio
fissato per il **1 ottobre 2026**. Tutto ciò che Google ha indicizzato, che gli
aggregatori hanno salvato e che gira sui social usa gli indirizzi di quel sito,
e arriva qui. Le rotte stanno in `routes/pubbliche/legacy.php`.

- **L'ordine di inclusione in `web.php` è un vincolo, non una preferenza.**
  `legacy.php` sta fra `sito.php` e `shop.php`: dopo le rotte vere, che devono
  vincere sugli indirizzi legacy con lo stesso prefisso (`/gallery/data` prima
  di `/gallery/{any}`, o l'archivio smette di caricare le foto), e prima della
  rotta generica `/{slug}` che chiude `shop.php` — dopo quella una redirezione
  a un segmento non verrebbe mai raggiunta. Dentro il file, i feed vanno prima
  di tag e categorie, che altrimenti se li prendono.
- **Le notizie non hanno una tabella di redirect: hanno lo slug.** Su WordPress
  stavano alla radice (`/titolo-della-notizia/`), qui sotto `/news/{slug}`, ma
  lo slug l'import l'ha conservato. `App\Support\PermalinkVecchioSito`, chiamata
  da `PageController@show` subito prima del 404, cerca fra le notizie pubblicate
  e fa 301. Una tabella sarebbe una seconda copia da riallineare a ogni slug che
  la redazione corregge.
- **Si guarda solo dalla rotta generica** (`request()->routeIs('*pages.show')`):
  lo stesso controller serve anche `/societa/{slug}`, `/youth/{slug}` e le altre
  sezioni, dove il vecchio sito non ha mai messo notizie.
- **La pagina del CMS vince sulla notizia omonima**, perché la ricerca parte
  dopo che il CMS non ha risposto: in produzione è il caso di `cartelle-stampa`,
  pagina della sezione Comunicazione e insieme vecchio comunicato.
- **Quello che non si riconosce resta 404.** Dei 2988 permalink del sitemap di
  Yoast solo 938 hanno un post in archivio: gli altri 2048 sono l'archivio
  2014-2021 che non è mai stato importato. Mandarli tutti su `/news` sarebbe un
  soft 404 per Google e nasconderebbe il buco. Stessa regola per gli eventi una
  tantum del vecchio sito (`/health-perfomance-conference`).
- **`config('app.locale')` non dice qual è la lingua predefinita**: a richiesta
  in corso `App::setLocale()` l'ha già riscritta con la lingua corrente, quindi
  il confronto `$locale === config('app.locale')` è sempre vero e il prefisso
  `en.` dei nomi di rotta sparisce (le notizie inglesi finivano sull'indirizzo
  italiano). Il confronto va fatto con `app.fallback_locale`, che nessuno tocca.
  In `web.php` la forma con `app.locale` è corretta perché lì si è ancora in
  fase di registrazione delle rotte.
- **`?p={wp_id}` è un indirizzo, non un residuo.** È la forma con cui WordPress
  indirizza un post senza slug ed è il `guid` che il vecchio feed pubblicava:
  `PublicController@home` lo risolve prima di costruire la pagina.

I vecchi indirizzi del feed (`/feed/atom/`, `/comments/feed/`,
`/news-c/{cat}/feed/`, `/tag/{x}/feed/`) portano tutti al feed di oggi
(`news.feed`), non all'archivio HTML: chi legge un feed non saprebbe che farsene
di una pagina.

Test in `tests/Feature/PermalinkVecchioSitoTest.php`.

---

## 24. Avvisi per email

Mappa completa in `docs/INFRASTRUCTURE.md` §9 (Avvisi). Vincoli:

- **Gli avvisi urgenti passano da `App\Services\AvvisoTecnico`**, non dalla sola
  campanella del pannello, che si vede solo entrando. I destinatari sono
  `AVVISI_EMAIL`, non i Super Admin: in produzione lo è tutta la redazione.
- **Invio sincrono, mai in coda, mai un'eccezione verso chi chiama**: fra i
  guasti da segnalare ci sono la coda ferma e i job falliti, e l'avviso parte
  anche da webhook di pagamento e health check.
- **`shop:sorveglia` guarda lo stato, non gli errori** (negozio spento, checkout
  senza metodi di pagamento, aste accese senza Stripe né PayPal per il
  vincitore, aste accese senza Stripe — la verifica della carta per offrire
  passa solo da lì —, worker fermo, PayPal) e avvisa quando la condizione cambia,
  non a ogni giro. Il primo giro senza stato precedente avvisa se trova il
  negozio spento, al massimo una volta al giorno; un errore di rete, un 5xx o
  un 429 di PayPal (`paypal:verifica` esce con `TRANSITORIO`) non valgono né
  come guasto né come guarigione. Non guarda gli ordini in attesa: sono
  checkout abbandonati, e un incasso che non torna avvisa già da sé.
- **Lo stato degli avvisi sta nello store di cache `persistente`**
  (`AvvisoTecnico::memoria()`, tabella `cache_persistente`), mai in quello
  predefinito: `start.sh` fa `cache:clear` a ogni avvio, e silenziatori e
  stato di `shop:sorveglia` lì dentro rimandavano gli avvisi a ogni rilascio.
  Un silenziatore o uno stato nuovo di un avviso va lì.
- **Un invio fallito non consuma il silenziatore** di `AvvisoTecnico`, né lo
  stato di chi ricorda di aver avvisato: `invia()` restituisce `EsitoAvviso`, e
  sorveglianza, health check e job falliti scrivono lo stato solo se l'esito
  non è `Fallito`. Il client di Resend ha un timeout (10 s, in
  `AppServiceProvider`); l'avviso del pianificatore parte con `defer()`, dopo
  la risposta di `/up`.
- **Il webhook di Resend avvisa solo per i clienti**: destinatario con un
  ordine negli ultimi 30 giorni, al massimo 10 avvisi l'ora. Newsletter e
  ricevute del recesso vanno a indirizzi scritti da chiunque: quei rimbalzi
  restano nel log, senza l'indirizzo.
- **I job falliti della coda `ai` non vanno per email**: dipendono da
  CompreFace e si recuperano da soli al giro orario.
- **Le destinazioni degli alert di DigitalOcean non stanno nella spec**: una
  regola nuova in `alerts:` non arriva a nessuno finché non le si imposta con
  `doctl apps update-alert-destinations`.
- **Gli errori JavaScript passano dal tunnel `/api/diagnostica`**
  (`resources/js/diagnostica.js`, `SentryTunnelController`): il browser non
  contatta Sentry, che quindi non riceve l'IP del visitatore — è ciò che
  l'informativa promette. Mandarli direttamente a `sentry.io` la renderebbe
  falsa. Solo gli script del sito (`allowUrls`), niente BrowserSession né props
  dei componenti (`attachProps: false`): ogni issue nuova è un'email in
  `allarmi@`. Il tunnel inoltra solo gli item `event` e ha il limiter
  `diagnostica` (30/min per IP, 300/min globale).
- **Il browser ha un progetto Sentry suo** (`SENTRY_BROWSER_DSN`,
  `SentryDsn::perIlBrowser()`): la quota di eventi è per progetto, e un errore
  JavaScript ripetuto su migliaia di visite la esaurirebbe, zittendo gli errori
  del server. Il tunnel accetta solo i due DSN del sito; vuoto, il browser
  ripiega su quello del server.
- **Il webhook di Resend punta all'indirizzo `ondigitalocean.app`**, non al
  dominio: resta valido dopo il 1 ottobre. La firma è Svix con
  `RESEND_WEBHOOK_SECRET` (livello d'app); senza segreto ogni notifica è
  rifiutata.

---

## 25. Consumatori: condizioni, recesso, prezzi, account

Mappa completa in `docs/CONSUMATORI.md`. Vincoli:

- **Il pulsante che chiude l'ordine dice «Ordine con obbligo di pagamento»**
  (art. 51 c. 2 del Codice del consumo), sulla seconda riga di
  `PulsanteOrdine.vue`: senza, il contratto non vincola il cliente. Vale per il
  checkout dello shop e per quello delle aste. Non spostarla fuori dal pulsante.
- **Condizioni di vendita e recesso sono pagine CMS con i testi in
  `database/data/condizioni_di_vendita.php`**, come le informative (§21). La
  versione accettata finisce su `orders.condizioni_versione`; si alza
  `CondizioniDiVendita::VERSIONE` quando cambia la sostanza. Ma la prova è
  l'istantanea: la redazione riscrive le pagine dal pannello, quindi ogni
  ordine (shop e aste) porta lo sha256 del testo pubblicato
  (`orders.condizioni_impronta`) e il testo sta in `versioni_condizioni`; il
  PDF della conferma si genera da lì (`perLAllegatoDellOrdine`), non dalla
  pagina di oggi. Quelle righe non si cancellano.
- **La conferma d'ordine porta venditore, recesso con modulo tipo, garanzia e
  il PDF delle condizioni** (art. 51 c. 7): il partial
  `emails/partials/informazioni-contrattuali` non si toglie.
- **Il prezzo barrato non è il listino**: è il più basso dei 30 giorni prima
  dello sconto (`StoricoPrezzi`, art. 17-bis). Senza storico precedente non si
  barra niente, e `price` arriva al frontend già scontato. Non tornare a
  confrontare `sale_price` con `price` per decidere cosa barrare.
- **Newsletter con doppio opt-in**: `confermato_il` nullo significa che
  l'indirizzo non esce dal sito. `SyncNewsletterToActiveCampaign` lo verifica
  da sé, qualunque sia la strada che lo accoda. Nei log l'id, mai email o IP.
- **Il consenso ai cookie scade dopo 12 mesi** (`DURATA_GIORNI` in
  `consenso.js`, quello che la Cookie Policy dichiara); la X del banner
  rifiuta; dopo la revoca il Pixel non manda più eventi e cancella `_fbp`.
  Chi decide se far partire un tag guarda `scelto && statistiche`, mai la sola
  casella.
- **Il recesso online sta su `/recesso`** (art. 54-bis): due passaggi, la
  ricevuta parte subito e in modo sincrono (è parte dell'obbligo), e le righe
  di `richieste_di_recesso` non si cancellano dal pannello. Il limite della
  POST è stretto (3 ogni 10 minuti) perché manda un'email a un indirizzo
  scritto da chi compila. Il link sta nel footer di ogni pagina: non toglierlo.
  In inglese è `/en/withdrawal` (`/en/recesso` fa 301): i link passano da
  `route('recesso')`. Le dichiarazioni si tengono **10 anni se agganciate a un
  ordine** (prova del recesso, prescrizione del rimborso), 12 mesi senza. La
  dichiarazione si registra sempre: il tetto di tre al giorno per indirizzo
  ferma solo l'email della ricevuta, e al titolare di un ordine altrui va
  `AvvisoDiRecessoAlTitolare`, senza i dati di chi ha scritto.
- **Le righe personalizzate (firma della giocatrice) sono escluse dal
  recesso** (art. 59 c. 1 lett. c): in `/recesso` non si selezionano e
  `RecessoController` le rifiuta; scheda prodotto, checkout ed email lo
  dicono. La lista degli articoli compare solo a chi ha il token dell'ordine
  o l'account: il solo numero d'ordine non basta a leggere un ordine altrui.
- **Il pixel della newsletter si revoca da solo** (linee guida del Garante
  del 17/04/2026, adeguamento entro il 29/10/2026): pagina preferenze firmata
  (`NewsletterSubscriber::preferenzeUrl`), `tracciamento_revocato_il`, tag
  `senza-tracciamento` su ActiveCampaign. ActiveCampaign non spegne il pixel
  per contatto: la redazione manda al segmento col tag una campagna con il
  tracciamento spento (`docs/ANALYTICS.md`).
- **L'avviso armonizzato UE sulla garanzia** (`AvvisoGaranziaLegale.vue`) sta
  sotto la casella del checkout, nella scheda prodotto e nell'email: sono le
  immagini ufficiali della Commissione, non si ridisegnano.
- **Il cliente si cancella e scarica i dati da `/shop/account`**
  (`AccountController`, `DatiDelCliente`). Gli ordini restano con `user_id`
  nullo (conservazione fiscale); gli account della redazione e chi ha un'asta
  in corso o da pagare non si cancellano da lì.
