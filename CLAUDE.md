# CLAUDE.md — Sito Savino (Savino Del Bene Volley)

> Istruzioni permanenti per Claude (Code, connettori MCP, chat). Unica fonte delle
> regole di progetto. Qui solo i vincoli da non rompere: storia e dettagli stanno
> nei `docs/` citati e nei commenti del codice.
> La numerazione dei § e' quella storica, citata da codice e docs: non rinumerare.

## 1. Contesto

- **Laravel 13** (Filament CMS, Inertia + Vue, **SSR non attivo**), **MySQL 8.4
  gestito su DigitalOcean** (App Platform `fra1`: web + worker + scheduler).
- In produzione su **`savinodelbenevolley.it` dal 1/10/2026**.
- File chiave: `.do/app.yaml`, `docker-compose.yml`, `docs/INFRASTRUCTURE.md`,
  `docs/GO_LIVE.md`, `tailwind.config.js`.

## 2. Database: connessione

- La chat "Home" gira in una sandbox **senza rete**: non tentare connessioni da lì
  ("Temporary failure in name resolution" è il blocco della sandbox, non le
  credenziali). Usare **Claude Code** o il **connettore MCP MySQL**.
- **Produzione**: cluster `sito-savino-db`, porta 25060, SSL obbligatorio (CA di
  DO), utente **`readonly_savino`** (solo `SELECT`). Host, DB e password **non
  sono nel repo** (pubblico): DO → Databases → Connection Details, tenuti in env
  locali o del connettore. **Mai `doadmin`.** L'IP che si connette va nelle
  Trusted Sources del cluster.
- **Connettore MCP**: `npx -y @benborla29/mcp-server-mysql` con `MYSQL_HOST`,
  `MYSQL_PORT=25060`, `MYSQL_USER=readonly_savino`, `MYSQL_DB=defaultdb`,
  `MYSQL_SSL=true` e `MYSQL_PASS` da variabile d'ambiente; config in `.mcp.json`
  (non tracciato). Utente creato con `GRANT SELECT ON defaultdb.* TO 'readonly_savino'@'%';`.
- **Locale**: `127.0.0.1:3306` (Homebrew) o `:3307` (Docker 8.4),
  DB/utente `sito_savino`, password `secret`; test su `sito_savino_test`.

## 5. Sicurezza sul DB e protocollo

- Sola lettura di default. `INSERT/UPDATE/DELETE/DROP` solo su richiesta
  esplicita, dopo averle spiegate e fatte confermare; modifiche correlate in
  transazione. Mai credenziali in chiaro.
- Prima di lavorare: `SELECT 1+1;`, poi elenco tabelle. Alla fine ri-testare
  (query o test del progetto) prima di dichiarare finito.
- Chat lunghe: breve riepilogo dei punti fermi per la chat nuova. Aggiornare
  questo file quando emergono regole stabili.

## 9. Database: versioni e vincoli

- Produzione **MySQL 8.4** (9.x non esiste su DO managed); locale 9.x, test su
  `sito_savino_test`. **Niente funzionalità solo 9.x.**
- Redis in locale; in produzione sessioni/cache/code usano `database`.
- **Colonne translatable spatie: sempre `text`**, mai `varchar` (errore 1406)
  né `json` (una riga legacy in testo semplice fa fallire l'ALTER e blocca
  l'avvio: le migrazioni girano a ogni deploy). Le `json` storiche
  (`shipping_zones.name`, `auctions.title/description/charity_description`) si
  convertono quando si tocca la tabella; le migrazioni allarganti hanno
  `down()` no-op documentato.
- **Lingue**: solo `config('app.supported_locales')` (`['it','en']`), mai
  riscritte a mano.
- **Ricerca CMS sulle colonne tradotte**: il content driver del plugin translatable
  è sostituito da `App\Filament\Support\TranslatableContentDriver` (bind in
  `AppServiceProvider::register()`). L'originale sbaglia con le maiuscole e va in
  errore 3141 sul testo semplice. Non togliere il bind senza
  `tests/Feature/Filament/TranslatableSearchTest.php`.

## 10. Deployment e dominio

- App Platform, spec **autorevole** in `.do/app.yaml` (cosa si aggiunge dal
  pannello DO viene cancellato al deploy successivo). Deploy automatico da
  `main`, gated dalla CI. File su **Spaces** `fra1`.
- **SSR non attivo** (`INERTIA_SSR_ENABLED=false`, nessun `ssr.js` né bundle).
- **Node 22**, fissato in `.nvmrc` (workflow) e in `engines` di `package.json`
  (build di App Platform): devono coincidere.
- **Dominio** (dal 1/10/2026, `docs/GO_LIVE.md`): `domains:` nella spec con
  `savinodelbenevolley.it` PRIMARY e `www.` ALIAS. `APP_URL` = `https://${APP_DOMAIN}`,
  quindi email in coda, sitemap, feed e ritorni dei pagamenti usano il dominio.
  `TRUSTED_HOSTS` = `savinodelbenevolley.it,seashell-app-47mmf.ondigitalocean.app`
  su **tutti e tre** i componenti: con la variabile scritta l'host di `APP_URL`
  non si aggiunge da solo, e senza l'indirizzo `ondigitalocean.app` prende 400.
  Lì restano **apposta** il webhook di Resend, quello di Stripe e la
  sorveglianza (valgono indipendentemente dal DNS). Il DNS è della Spa (IT:
  `tech.sdb.it`), non nostro.
- **Un solo indirizzo per chi naviga**: `PortaSullIndirizzoDelSito` (globale) fa
  301 delle GET di `www.` e `*.ondigitalocean.app` sull'host di `APP_URL`
  (`X-Inertia-Location` per le visite Inertia). Esclusi POST, `api/*` (webhook)
  e `/up`.
- **Webhook PayPal**: punta a `https://savinodelbenevolley.it/api/webhooks/paypal`.
  Si **modifica, mai si ricrea**: `PAYPAL_WEBHOOK_ID` entra nella verifica della
  firma, un webhook nuovo lascia gli ordini "pending" senza errori. Controllo:
  `php artisan paypal:verifica`.
- `php artisan verifica:lancio` (dalla console di **ciascun** componente) dice
  blocchi e cose da guardare su posta, pagamenti, shop, notizie, `APP_KEY`.
- **Posta via Resend** (dal 25/09/2026): `MAIL_MAILER`, `MAIL_FROM_*`,
  `RESEND_API_KEY` a livello d'app. Il mittente vale per il DKIM verificato su
  Resend (DNS della Spa con `DMARC p=reject`): se quei record spariscono le
  email vengono **rifiutate**. Dettagli in `docs/GO_LIVE.md` §1.
- **Web, worker e scheduler non condividono variabili**: ciò che sta solo sotto
  `services:` non arriva a coda e scheduler. Il caso peggiore è `APP_KEY`
  (sbagliata, `encrypt()` e URL firmati non combaciano, senza errori a vista).
  Controllo: `doctl apps console <app> <componente>` e
  `php -r 'echo strlen(getenv("APP_KEY"));'`. Le tre liste le confronta
  `tests/Unit/VariabiliAllineateFraIComponentiTest.php`, con le eccezioni
  (variabili solo per richieste HTTP) motivate una per una.

## 11. Brand e accessibilità

- Palette, loghi e regole: `docs/BRAND_GUIDELINES.md`. Font: Montserrat (sans) e
  Playfair Display (serif).
- **Colori**: ufficiali blu #003063, rosso #DF338F, fucsia #F8269C, rosa #ED028C.
  Nei token Tailwind fucsia/rosa/rosso sono **scuriti** per il contrasto WCAG
  2.1 AA (obbligatorio: European Accessibility Act, la società non è
  microimpresa): `savino-fucsia` #D00778, `savino-pink` #D0027B, `savino-red`
  #C91F7A. Le tinte ufficiali restano `*-brand`, solo decorative.
  - fucsia su blu/grigio scuro/foto/gradienti scuri (anche `hover:`):
    `text-savino-fucsia-chiaro` (#FA5FB6);
  - fucsia su tinte chiare di fucsia o sul grigio #e8eaef: `text-[#B8066A]`;
  - su fondo fucsia il testo è bianco; nelle email pulsanti e testi fucsia #D00778.
  - `savino-gold` non esiste più; l'oro resta solo nelle serie dei grafici.
- **Controlli automatici**: `scripts/scansione-accessibilita.mjs` (axe) ogni
  lunedì in `scansione-accessibilita.yml`, fallisce sulle violazioni gravi. Sul
  sito vero solo letture; i flussi (carrello, checkout, conferma, asta) girano in
  CI con `ScansioneAccessibilitaSeeder` (`--flussi` rifiuta host non locali). In
  `npm test` axe + screen reader simulato (`resources/js/testing/`); prova umana
  in `docs/ACCESSIBILITA.md`. Lint con `eslint-plugin-vuejs-accessibility`.
- Il pulsante d'ordine non si spegne mai in silenzio: al clic dice cosa manca. I
  PDF passano da `PdfAccessibile`. Pagina `dichiarazione-di-accessibilita`
  (testo iniziale in `database/data/dichiarazione_accessibilita.php`): i limiti
  noti si tolgono quando si risolvono.
- **Loghi**: sempre via `LOGOS` di `resources/js/Constants/logos.js`, mai
  percorsi a mano. File in `public/images/`, sorgenti in `Loghi/`.
  - Il marchio corporate si serve **SVG** (estratto dai PDF ufficiali, non
    ridisegnato): il PNG ridotto spezza le 93 righe del cubo.
  - **Sotto 151 px CSS (40 mm) niente payoff**: `logo-corporate-name*.svg`.
    `--corporate-logo-w` = `--volley-logo-h` × 1,9: rimpicciolire il volley
    porta il marchio sotto soglia.
  - Testata centrata sul logo volley: `--header-h` = `--volley-logo-h` + 16 px
    (`PublicLayout.vue`), letta anche dall'hero della home.
  - LVF 2026/27: versione "SERIE A", non modificarne colori né lettering. Il
    magenta LVF #FF23B0 sta solo nel suo logo, non è un token.

## 12. Lega Volley Femminile (LVF)

Calendario, risultati, classifica e tabellini **non si inseriscono a mano**:
`php artisan lvf:sync [--season=2026]` (orario; anno = apertura di stagione)
parsa le pagine pubbliche di `legavolleyfemminile.it`. Codice in
`app/Services/Lvf/`, config `services.lvf`, test su fixture vere in
`tests/Fixtures/Lvf/` (se cambia il markup: fixture, poi parser).

- **Idempotenza**: upsert su `games.lvf_match_id` e `teams.lvf_club_id`.
- **Il lavoro manuale non si tocca**: le gare senza `lvf_match_id` non vengono
  mai modificate né cancellate.
- `teams` contiene squadre interne (`is_internal = true`) e avversarie: una
  interna senza flag viene **duplicata** al primo sync. Squadra creata dal
  pannello = interna; le avversarie arrivano solo dai sync.
- **La Lega rinumera le società ogni stagione** (Savino `710955` nel 2026/27,
  `710918` nel 2025/26): gli alias stanno in `team_lvf_club_ids`. Risoluzione:
  alias → `services.lvf.club_ids` (da aggiornare a ogni stagione) → nome esatto.
- **Loghi**: collezione `logo` (CMS) e `logo-lvf` (import, che scrive solo lì).
  Usare `Team::logoUrl()`.
- Pagina pubblica: tutto il campionato, gare del Savino marcate `isOwn`;
  ordinate per data e raggruppate per giornata **e fase** (le giornate 1-13 si
  ripetono fra andata e ritorno).
- **Tabellini** solo per le gare della società (26 su 182), da
  `ww5.legavolleyfemminile.it/TabellinoGara_i.asp?IdGara=<lvf_match_id>`
  (Windows-1252, riconvertita da `LvfClient::boxScore()`). Servizio
  `LvfStatsSyncService`, parser `LvfBoxScoreParser`, tabella `game_player_stats`
  con le righe di entrambe le squadre (`player_id` solo per le nostre, match sul
  nome normalizzato). Il parser riconosce la tabella vera dalle
  sotto-intestazioni (`Tot`, `BP`): sbagliare attribuisce le atlete all'avversaria.
- `player_stats` si **ricostruisce** dai tabellini, non si incrementa.
- `sync:legavolley` genera dati **simulati** e rifiuta la produzione: non è una fonte.

## 12-bis. Gallery: la cache si rigenera

- **La cache della gallery si rigenera, non si butta**: `GalleryArchive` (12 000+
  foto, ~10 s per costruirla) in cache un giorno; `CacheInvalidationObserver`
  accoda `RicostruisciLaCacheDellaGallery` invece di cancellare
  `public:gallery_images:<locale>`. Le varianti per atleta e le pagine intere
  (`CachePublicResponse`) si buttano cambiando generazione (`GenerazioneDiCache`,
  una scrittura), mai chiave per chiave; le righe scadute le toglie
  `cache:pota-scadute` (il driver `database` non lo fa da sé).

## 12-ter. Riconoscimento dei volti (CompreFace)

- **CompreFace** su un droplet raggiungibile **solo dalla VPC**
  (`COMPREFACE_HOST`); chiave vera nel suo Postgres. Le foto di addestramento
  non restano né nel repo né su Spaces.
- **Il pubblico non si confronta** (informativa del 5/10/2026): il servizio di
  rilevamento (`COMPREFACE_DETECTION_KEY`) trova i volti, quelli sotto
  `quota_volto_riconoscimento` (4% del lato corto, non pixel) si coprono
  (`VoltiDiSfondo`) prima del riconoscimento. Senza chiave di rilevamento il
  riconoscimento si ferma e avvisa.
- `players.ai_face_examples` è una copia: la riallinea ogni notte
  `volti:riconcilia-contatori`.
- Le foto entrate senza l'upload del pannello si analizzano con
  `gallery:analyze --pending --limit=600` (orario): un nuovo canale d'ingresso
  lascia solo `ai_analyzed_at` nullo.
- Addestramento: **solo primi piani con un volto**, mai miniature né volti
  piccoli (`addFaceExample` rifiuta sotto `services.compreface.min_face_px`,
  90). Cinque esempi scadenti hanno prodotto 123 tag falsi al 99%: confrontare
  sempre i tag con la stagione in cui l'atleta era in squadra.
- "Da rivedere" = somiglianza fra 0.97 e la soglia del tag (0.985) su un volto
  ≥ 80 px; il job lo scrive in entrambe le direzioni.
- **Tag fuori stagione**: il job scarta un'atleta su foto di stagioni in cui
  non era in squadra (`StagioniDelleAtlete`: stagioni passate in
  `database/data/stagioni_delle_atlete.php`, la corrente dalle rose; atleta
  assente dal file = non giudicata). Un'atleta nuova con passato al Savino va
  aggiunta lì. Pulizia degli esistenti: `volti:togli-fuori-stagione [--dry-run]`.
- **Il job non toglie mai tag**: per rifare un'analisi cancellare le righe di
  `gallery_image_person` con `confidence_score` non nullo (le manuali l'hanno
  nullo) e azzerare `ai_analyzed_at`.
- I batch con `allowFailures()` non si chiudono se un job fallisce:
  `queue:prune-batches` li pota dopo tre giorni.

## 12-quater. CEV Champions League

Da `www-old.cev.eu/Competition-Area` (HTML statico; il sito nuovo è solo JS):
`php artisan cev:sync [--competizione=1948] [--season=2026]`, orario a metà ora.
Codice in `app/Services/Cev/`, test in `tests/Fixtures/Cev/`.

- **L'ID competizione cambia a ogni edizione** (`CEV_COMPETITION_ID`: 1948 =
  2026/27, 1802 = 2025/26) insieme a `services.cev.season_year`: aggiornarli con
  `services.lvf.club_ids`. Il parser si aggancia ai suffissi stabili degli id
  ASP.NET/Telerik (`_LB_FederationMatchNumber`, `_LB_DataOra`…).
- Idempotenza su `games.cev_match_id`; le gare senza restano come sono, e sulle
  importate si scrivono solo le colonne CEV (la diretta resta della redazione).
- Si pubblicano solo gare con data e due squadre, **tranne** quella della
  società con avversaria ignota: esce con `Team::SLUG_DA_DEFINIRE`
  («Avversaria da definire»). Dove si stampa il nome di una squadra di una gara:
  `nomePubblico()`, non `name`.
- **Ora locale del palazzetto** convertita via `services.cev.fusi_orari` (città
  in fondo al nome dell'impianto; assente = Europe/Rome: aggiungere le città
  nuove fuori dall'Italia).
- La nostra squadra si riconosce dal nome (`services.cev.nomi_della_societa`),
  non dal `TeamID`. Avversarie per nome esatto, create se mancano; loghi CEV no.
- Classifica: solo il girone della società. In pagina (`/stagione/cev`) gironi
  raggruppati per giornata; `games.phase` resta in inglese, tradotta in
  presentazione (`enums.game.phase`).

## 13. Analytics (`docs/ANALYTICS.md`)

- Credenziali (service account Google, segreti Meta) solo in env; in DB solo il
  Measurement ID di GA4.
- `WebAnalyticsService` e `SocialAnalyticsService` **non lanciano verso la UI**:
  restituiscono `error`/`degraded`.
- Serie giornaliere conservate (`web_analytics_daily`, `social_insights_daily`):
  i giorni `is_final` non si richiedono più (per Meta ogni giorno costa una
  chiamata). Tetto Meta: 15 chiamate a pagina, 120 nel comando notturno.
- Totali di periodo con `total_value`, mai somma dei giorni (reach).
- Senza `read_insights` Meta dà metriche vuote, non errori: il rilevamento in
  `FacebookPageInsights` resta.
- GA4 solo dopo il consenso statistico (`resources/js/analytics.js`, Consent
  Mode v2), con `page_view` a ogni visita Inertia.
- Il **Pixel di Meta** (`resources/js/meta-pixel.js`) parte solo col consenso di
  marketing (`META_PIXEL_REQUIRES_CONSENT`, predefinito `true`). `Purchase`
  deduplicato per numero d'ordine su `sessionStorage`.

## 14. Pagine CMS e contenuti

### `content_data`
- **Struttura piatta**: stesso nome di chiave nel form, nel Vue e nei file dati.
  Niente chiavi annidate.
- **Niente contenuti nei componenti Vue**: leggono solo ciò che arriva dal
  backend e nascondono la sezione vuota. I valori iniziali stanno in
  `database/data/` (`page_content_data.php`, `page_template_defaults.php`,
  `storia_timeline.php`…) o si mettono con una migrazione.
- **Si salva dal form deidratato** (`PreservaContentData`, via
  `$form->getState()` e `ContentData::normalizza()`), mai dallo stato grezzo di
  Livewire (Repeater = mappa `{uuid: voce}`, la sezione sparisce). I test
  verificano `array_is_list`.
- **Nessun campo si chiama `content_data` nudo**: un campo nascosto non viene
  deidratato e Filament toglie tutto ciò che sta sotto il suo percorso.
- **Una chiave, un tipo solo**: i template condividono lo spazio dei nomi. Le
  chiavi elenco stanno in `ContentData::CHIAVI_ELENCO` (un test le confronta coi
  Repeater); `EditPage::mutateFormDataBeforeFill` tiene fuori i valori di tipo
  sbagliato.
- **Lingue**: gli elenchi vuoti in inglese prendono quelli italiani
  (`ContentData::conGliElenchiDiRipiego()`), e così i valori senza lingua (chiavi
  in `_url`, `_image`, `_link`, `_email`, `_src`, `_value`, `_file`); un valore
  compilato in inglese vince. Gli elenchi senza testo da tradurre
  (`ContentData::CHIAVI_COMUNI`) si salvano in tutte le lingue
  (`EditPage::allineaLeChiaviComuni`). Le lingue diverse da quella iniziale
  arrivano al form grezze: `EditPage` le passa da `$form->fill()` (`lingueGrezze`).
- File caricati dentro `content_data`: dichiararli in
  `App\Support\CmsFile::resolveInContentData()`, o in produzione (Spaces) il link
  è rotto.
- **Una porta sola verso il frontend**: `Page::datiPerIlFrontend()` (ripieghi,
  file, video, documenti legali). Ogni rotta che pubblica una `Page` passa di lì.

### Pannello Filament
- Repeater facoltativi: `defaultItems(0)`.
- Un campo `hidden()` (anche `Select`) non viene deidratato: per valori fissi
  `Forms\Components\Hidden`.
- Un modulo che mostra una sola chiave di una colonna JSON la riscrive tutta:
  fondere con `mutateFormDataUsing`.
- Pagine delle impostazioni: idratare con `$this->form->fill()`, non `$this->data = …`.
- Per confrontare campi: `->lte('price')`, `->after(...)`, mai `->rule('lte:price')`
  (i dati stanno sotto `data.`: la regola fallisce sempre).
- Una colonna fuori da `$fillable` non si scrive dal modulo e non protesta.
- Modelli di pagina solo in `App\Enums\PageTemplate` (un test verifica il `.vue`).
- **Foto dal pannello**: ogni `FileUpload` salva passando da
  `App\Support\FotoAlleggerita` (configurazione "important" in
  `AppServiceProvider`: il `setUp()` del campo vince sulle normali). Ai PNG si
  toglie il profilo `iCCP` (sul GD di Linux un profilo difettoso manda in 500
  le conversioni); i JPEG scendono a 2560 px, qualita' 86, ICC ricopiato. PNG
  e WebP non si ridimensionano (il GD di Linux perde la trasparenza). La
  gallery resta originale (CompreFace, che riceve una copia sotto 5 MB).
  Limite per file = `media-library.max_file_size` (50 MB). PNG gia' rotti:
  `php artisan foto:ripara-png`.
- `CmsPagesSeeder` **non si rilancia in produzione**.
- Revisione del lavoro della redazione: `activity_logs`, solo per le azioni
  dello staff: autore, IP e browser si scrivono solo per chi entra nel
  pannello; Order, User e StockMovement hanno `$logSoloDalPannello` e i campi
  personali in `$logExclude`; carrelli, righe d'ordine, offerte e coupon usati
  non si registrano. Pulizia a 180 giorni (`activity-log:prune --force`).
  Correzioni ai testi in
  produzione con **migrazione a guardie** (tocca solo il valore ancora
  sbagliato), provata a secco su una copia delle righe.

### Impostazioni (`SiteSetting`)
- `get()` accetta `chiave` e `gruppo.chiave` (prima la chiave letterale, poi il
  gruppo). **Mai creare in una migrazione la forma `gruppo.chiave`**: oscura il
  valore salvato nel gruppo.
- Chiavi nuove di un gruppo: con una migrazione (`set()` le mette nel gruppo
  predefinito). Le `json` si traducono come le altre (`resolveForLocale()`).
- Recapiti e dati societari: gruppo `contact`, non `content_data`. Le caselle
  dei singoli uffici (`Page::CASELLE_PER_MODELLO`) non viaggiano nelle props
  condivise: le riceve solo la pagina del loro modello (`page.caselle`).

### Regole varie
- **Una sezione, una pagina**: non ricreare pagine con lo slug di una sezione
  (`/youth`, `/ticketing`, `/sociale` sono redirect).
- Meta description dalla colonna `meta_description`.
- Galleria di pagina: collection media `gallery` su `Page`, mai `/storage/...` a mano.
- Coda multimediale condivisa: `PageMediaTail.vue` / `PageTemplateForms::mediaTailSchema()`.
- Indirizzi scritti dalla redazione: `safeUrl`. Link esterni: mai `<Link>` di
  Inertia (`isExternalLink()`/`externalLinkAttrs()` di `menuLinks.js`); link
  interni con `route()`, non `href="/..."`.
- Un indirizzo che non esiste risponde **404**, non 200. Sitemap con
  `PageController::percorsoPubblico()`.
- **Menu**: le etichette pesano sul layout (`useHeaderNavFit`): misurare prima
  di allungarle. Una sola voce `is_highlight` (diventa `ShopCtaButton`).
  "Foto Ufficiale" sparisce finché manca il PDF.
- Accrediti stampa = `ContactMessage` con oggetto
  `PressAccreditationController::SUBJECT` (legame con `PressAccreditationResource`).
  La conferma al richiedente parte solo da «Accredita e avvisa» / «Reinvia
  conferma» (`PressAccreditationResource::accredita`), nella lingua salvata in
  `extra_data.lingua`; cambiare lo stato dal modulo non scrive a nessuno.
- Album della gallery: `/gallery/album/{id}-{titolo}`, conta solo l'id; aprire
  e chiudere un album cambia indirizzo con `router.push` lato client.
- Squadre del vivaio per `teams.category` (`B1`, `U17`, `U15`), non per slug.
- Template specifici: Ticketing (`TicketingTemplateForm`, blocchi facoltativi,
  la biglietteria non ha listino), Convenzioni (`partners`), Affiliazioni
  (`affiliates`, `AffiliateTier`),
  Club Race (`standings`), Safeguarding (documenti per chiave da Documenti
  Legali, `DocumentiLegali::risolviNeiDocumenti`). Sezioni nuove nascono
  facoltative; il testo dell'editor è l'introduzione sotto l'hero.
- Ordine categorie news: `categories.sort_order`, non riordinare nel frontend.
- `/stagione/risultati?squadra=savino` apre il calendario filtrato.
- `players.instagram_handle`: nome utente, esposto da `Player::instagramUrl()`.
- Mappa del Palazzetto: `content_data.maps_iframe_src` (solo il `src` Google).

### Notizie e vecchio sito
- **Archivio chiuso al 27/09/2026**: gli import dal vecchio WordPress (notizie,
  media, gallery, documenti, affiliazioni, sponsor) sono stati tolti dopo il
  passaggio del dominio, con le migrazioni che li lanciavano ora no-op. Le
  notizie nascono solo nel pannello; i loro media vecchi stanno su Spaces sotto
  `news/<anno>/<mese>/`.
- Resta `posts.wp_id`, che serve ai `?p=` del vecchio sito (§23).
- Le date delle notizie importate sono ora locale di WordPress: il connettore
  MCP le mostra spostate, leggerle con `CAST(published_at AS CHAR)`.

## 15. Sponsor

- Livelli in `App\Enums\SponsorTier` (ordine dei case = ordine in pagina);
  raggruppamento in `App\Services\SponsorDirectory`, condiviso. L'elenco lo cura
  la redazione. Le richieste vanno a `marketing@savinodelbenevolley.it`.

## 16. Diretta streaming

- **Diretta**: `games.stream_url` (redazione). `LiveStream::embedUrl()` accetta
  solo YouTube (`youtube-nocookie`, senza autoplay), Vimeo, Twitch, Dailymotion;
  il resto si apre in una scheda. Non allargare la lista senza valutare;
  `frame-src` della CSP va allineato.

## 17. Pagamenti (Stripe, PayPal, bonifico)

- **Verso il gateway `Inertia::location()`, mai `redirect()->away()`** (la POST
  è XHR e muore sul CORS con l'ordine già creato). Vale per shop e aste.
- Indirizzi di ritorno solo da `Order::successUrl()` / `cancelUrl()`.
- **PayPal incassa in due punti**: al ritorno (`CheckoutController::success`) e
  nel webhook `CHECKOUT.ORDER.APPROVED`; idempotenza sulla coppia (ordine,
  transazione), `ORDER_ALREADY_CAPTURED` si rilegge. Il numero d'ordine si cerca
  anche nell'evento e nel `reference_id`. La firma si verifica sul **corpo grezzo**.
- **Stripe live dal 1/10/2026**: `STRIPE_*` a livello d'app (cifrate da DO: si
  ricopiano da `doctl apps spec get` o il deploy le cancella). Webhook
  `sito-savino-shop` su `…ondigitalocean.app/api/webhooks/stripe`: un evento
  nuovo nel codice va aggiunto anche lì. `checkout.session.completed` **non vuol
  dire pagato**: conferma solo con `payment_status = paid`; SEPA con
  `async_payment_succeeded`. Le contestazioni avvisano per email.
- **Aste**: stesso `Order`, stessi gateway (`PaymentGateway::offertiAlleAste()`).
  Il bonifico sposta il termine del vincitore
  (`OrdineDelVincitoreDellAsta::terminePerIlBonifico`). Un pagamento arrivato con
  l'asta già passata ad altri va in revisione da rimborsare
  (`HandlesPaymentWebhooks::astaPassataAdAltri`).
- Importo incassato ≠ totale: meno → registrato ma ordine in revisione; più →
  confermato e segnalato.
- Ordine con `payment_id` non si annulla né si ripaga.
- Gateway senza credenziali non si offre (`PaymentGateway::configurato()`).
- Giorni del bonifico: solo `PaymentGateway::giorniPerIlBonifico()`.
- Ogni acquisto avvisa la società (`AvvisoNuovoOrdine`,
  `shop.order_notification_emails`) negli stessi punti della conferma al
  cliente: un nuovo canale d'ordine deve chiamarlo.

## 18. Sicurezza HTTP

- `SecurityHeadersMiddleware` serve **due CSP**. **Sito pubblico**: niente
  `unsafe-inline`/`unsafe-eval`, script in linea con **nonce**
  (`Vite::useCspNonce()`, `@routes(null, $cspNonce)`): un `<script>` nuovo nel
  layout senza nonce o un `onload=`/`onclick=` non vengono eseguiti.
  **Pannello** (`admin*`, `filament/*`, `livewire/*`, registrato in
  `AdminPanelProvider`): `unsafe-inline`/`unsafe-eval` per Alpine,
  `fonts.bunny.net`, `worker-src 'self' blob:` e `blob:`/`data:` in
  `connect-src` per FilePond. Test in `SecurityHeadersTest`.
- `CachePublicResponse` salva HTML e header insieme (nonce coerente): non
  rigenerare l'header su un cache hit. Non porta Set-Cookie: `bootstrap.js`
  chiede `/csrf-cookie` prima di scrivere; 419/429 Inertia tornano alla pagina.
  Esclude i crawler.
- **Ziggy a elenco chiuso** (`config/ziggy.php` `only`): una rotta nuova chiamata
  dal frontend per nome va aggiunta lì (`RotteNelBrowserTest`).
- `RispostaSenzaResidui` (globale): toglie `X-RateLimit-*` e fa scadere i cookie
  del vecchio WordPress. `/.well-known/security.txt` è una rotta: la cartella
  `public/.well-known/` serve ad aggirare il 403 del buildpack sui percorsi col punto.
- **Ogni `throttle:N,M` ha il terzo parametro** (nome del limite), o i limiti
  condividono il contatore (`LimitiDelleRotteSeparatiTest`).

## 19. Anteprime social

`ServeSocialCrawlerMeta` risponde ai crawler (`CRAWLER_PATTERNS`) con un HTML
minimale; per tutti gli altri mette gli stessi meta nell'attributo
`ATTRIBUTO_META`, che `app.blade.php` stampa (titolo, descrizione, `og:`) con
l'attributo `inertia` (Google senza SSR). Le visite Inertia non li calcolano.
- Pagina riconosciuta dal **nome della rotta** (uguale in tutte le lingue); le
  pagine CMS dal controller `PageController@show`.
- Ciò che non sa descrivere passa a `$next` (301 e 404 restano tali).
- Riapplicare gli scope pubblici (`Post::published()`, `Product::shoppable()`).
- Testi in `lang/*/site.php` → `social`, con la lingua passata a mano (gira
  prima di `SetLocale`). Test in `SocialCrawlerMetaTest`.

## 20. Shop

- **Impostazioni**: valori di partenza in `database/data/impostazioni_shop.php`,
  anche quando la riga non esiste (il modulo si apriva vuoto e il primo Salva ha
  spento il negozio). `shop.free_shipping_threshold` vuoto = vale la soglia della
  zona; compilato (> 0) vale per tutte le zone, applicata in
  `ShippingZone::calculateShippingCost`. Gli interruttori di shop e aste li decide la redazione, non una migrazione.
- `Auction::status` si cambia solo con `Auction::cambiaStato()` (`TRANSIZIONI_AMMESSE`).
  Il prodotto di un'asta esce e rientra dallo shop via `AuctionObserver`.
- Giacenza con varianti = somma delle taglie (`Product::availableStock`), non
  `products.stock`, che per loro è solo un riepilogo ricalcolato
  (`Product::riallineaLaGiacenzaDelleTaglie`, dai movimenti e da
  `ProductVariantObserver`): **mai scalarlo con la guardia** (il 02/10/2026 a 0
  bloccava il checkout delle taglie disponibili). Prodotto con taglie = taglia
  obbligatoria in carrello e nei movimenti.
- **Prezzo barrato** = il più basso dei 30 giorni prima dello sconto
  (`StoricoPrezzi`, art. 17-bis); senza storico non si barra. Lo sconto si
  annuncia solo mentre è in corso (`effectivePrice()`).
- **Etichette** (`products.etichette`, `EtichetteDelProdotto`): IN OFFERTA e
  ULTIMO RIMASTO solo se vere (Codice del consumo, artt. 21 e 23).
- Coupon su prodotti/categorie (`Coupon::valePerIlProdotto`): sconto sulla parte
  ammessa, ordine minimo sulla spesa intera. Il coupon sconta anche la firma (scelta).
- **Spedizione**: fasce in `shipping_zones.weight_rates`; soglia gratuita prima
  delle fasce; peso via `Product::pesoPerLaSpedizione()` (ripiego
  `shop.default_item_weight_kg`). **Conto scritto una volta sola**, in
  `ShippingZone::calculateShippingCost`: i checkout (shop e asta) ricevono il
  `costo_spedizione` per zona già calcolato. Non reintrodurre il conto nel client.
- Più categorie per prodotto: principale `product_category_id` + 
  `product_category_product`; usare `scopeNelleCategorie` / `idCategorie()`.
- Ordine di vetrina `products.sort_order` (trascinamento; `reorderTable` rimescola
  solo le posizioni occupate; nuovi prodotti in cima). Non azzerarlo a mano.
- Articoli collegati a senso unico, fuori cache; la cache `public:shop` la
  buttano anche taglie, magazzino e `prezzi:registra`.
- Guida taglie scelta sul prodotto (`App\Support\GuidaTaglie`), niente tabelle nel Vue.
- **Personalizzazione (firma)**: non è una variante; giacenza sulla somma delle
  righe (`CartService::quantitaPerPezzo`); prezzo solo da
  `CartItem::prezzoUnitario()`. Esclusa dal recesso.
- **Usato/autografato**: `stato_articolo` obbligatorio in italiano
  (`ProductResource::verificaStatoDellArticolo`, non `->required()`), fotografato
  sulla riga d'ordine (`Order::registraArticolo`).
- Ricevuta PDF con Montserrat incorporato (`resources/fonts/pdf`, solo 400/700).

## 21. Cookie e privacy (`docs/PRIVACY.md`)

- Consenso fatto in casa (Cookiebot scartato). La scelta si legge **solo** da
  `resources/js/consenso.js`; scade dopo 12 mesi; si parte se
  `scelto && statistiche`. `ConsensoCookie::VERSIONE` si alza solo quando cambia
  ciò che si dichiara.
- `consensi_cookie` è la prova del consenso: IP solo come impronta salata con
  `CONSENSI_SALE` (ripiego su `APP_KEY`: impostarla prima di ruotare la
  chiave, poi non cambiarla), 24 mesi (`consensi:pota`, tiene l'ancora in
  `consensi_cookie_potature`). Righe immodificabili e incatenate
  (`CatenaDeiConsensi`, controllo `consensi:verifica`); il testo visto
  (banner, Cookie Policy, elenco cookie; per la newsletter modulo e conferma)
  sta in `versioni_testi_consenso`, mai cancellate. Footer: «Preferenze cookie».
- Newsletter disiscritta: nome e IP via subito, la riga resta 24 mesi come
  prova (`NewsletterSubscriber::prunable`).
- Pixel sotto consenso marketing; CSP con `www.facebook.com` in `form-action` e
  `frame-src` (non è una piattaforma di `LiveStream`).
- La dichiarazione dei cookie viene da `database/data/cookie_rilevati.json`
  (workflow `scansione-cookie.yml`), descritta in `catalogo_cookie.json`: un
  cookie nuovo si spiega nel catalogo. La scansione senza consenso che diventa
  rossa **non si silenzia**.
- **Ogni iframe di terzi passa da `ContenutoIncorporato.vue`** (click-to-load):
  un `<iframe>` scritto a mano rompe l'informativa.
- Font serviti dal sito (`public/fonts`, `@font-face` in `app.css`), famiglia
  `Montserrat` (non `Montserrat Variable`: la usano Tailwind, email e `useHeaderNavFit`).
- Il checkout (shop e aste) linka `privacy-policy#acquisti`: un uso nuovo dei
  dati d'ordine si dichiara in quella sezione. `shop_events` non tiene IP né
  sessione.
- Informative: testi in `database/data/informative_privacy.php` e
  `informative_da_documento.php`, via `App\Support\TestiDelleInformative`;
  correzioni con migrazioni a guardie; le `firme` sono cumulative. Non
  ricopiarle in seeder o traduzioni. Tutte le voci legali del footer sono pagine.
- Conservazioni dichiarate = comando che le applica (`messaggi:pota` conta dalla
  data del messaggio).
- Titolare: «Pallavolo Scandicci Savino Del Bene Società Sportiva
  Dilettantistica a Responsabilità Limitata» (CF 94217750481, P. IVA 06271460484,
  sede Via Benozzo Gozzoli 5/6, 50018 Scandicci). Diritti a
  `privacy@savinodelbenevolley.it`, non `info@`.
- Riconoscimento dei volti dichiarato (consenso esplicito, art. 9 §2 a, raccolto
  fuori dal sito). Alla revoca: azione «Revoca consenso al riconoscimento» su
  atleta/staff (anche alla cancellazione della scheda): soggetto su CompreFace,
  righe AI di `gallery_image_person`, nome in alt/descrizione/parole chiave e nei
  titoli generati (`TestiSeoDellaFoto`; i titoli scritti a mano si segnalano).
  Le righe `revoca_volti` del registro non scadono.

## 22. Feed RSS

Richiesto dalla Lega, indirizzo pubblicato a terzi.
- Canonico **`/feed`** (`/en/feed`); `/news/feed` e `/rss` fanno 301. Non
  cambiarlo senza avvisare la Lega. `/news/feed` sta prima di `/news/{slug}`.
- **Il `guid` è `urn:savinodelbenevolley:notizia:<id>`** (`isPermaLink="false"`):
  cambiarlo ripubblica l'archivio.
- Contenuto: URL assoluti, `]]>` spezzati, caratteri di controllo tolti.
- Campi tradotti con `testoTradotto()` (trait `TestoTradotto`), non
  `getTranslations()`.
- Cache 30 min, buttata al salvataggio di notizie e categorie. Test `NewsFeedTest`.

## 23. Indirizzi del vecchio sito (`routes/pubbliche/legacy.php`)

- In `web.php` `legacy.php` sta **fra `sito.php` e `shop.php`**; dentro, i feed
  prima di tag e categorie.
- Le notizie vecchie (`/slug/`) trovano la nuova via `PermalinkVecchioSito`
  (solo dalla rotta generica `*pages.show`, dopo il CMS, che vince). Il resto
  resta **404**, niente redirect generici a `/news`.
- `?p={wp_id}` si risolve in `PublicController@home`.
- Vecchi feed → `news.feed`.
- **Lingua predefinita**: confrontare con `app.fallback_locale`, non
  `app.locale` (riscritta da `setLocale`). Prefisso lingua via
  `Route::has('en.…')`. Test `PermalinkVecchioSitoTest`.

## 24. Avvisi (`docs/INFRASTRUCTURE.md` §9)

- Urgenti via `App\Services\AvvisoTecnico` a `AVVISI_EMAIL`, **sincroni**, mai
  eccezioni verso chi chiama. Un invio fallito (`EsitoAvviso::Fallito`) non
  consuma il silenziatore né lo stato.
- **Ogni comando pianificato che fallisce avvisa** (`AvvisoDelPianificatore`,
  agganciato dal ciclo in fondo a `routes/console.php`, uno ogni sei ore per
  comando): `schedule:work` manda l'output in `/dev/null`. Un evento nuovo va
  registrato **sopra** quel ciclo, o resta muto.
- Stato e silenziatori nello store `persistente` (`AvvisoTecnico::memoria()`),
  mai nella cache predefinita (svuotata a ogni avvio).
- `shop:sorveglia` guarda lo stato (negozio spento, gateway, Stripe per le aste,
  worker, PayPal) e avvisa ai cambi; errori transitori non contano.
- Webhook Resend: avvisi solo per clienti recenti, max 10/ora. Job falliti della
  coda `ai`: niente email.
- Destinazioni degli alert DO fuori dalla spec (`doctl apps update-alert-destinations`).
- **Errori JS via tunnel `/api/diagnostica`**, mai diretti a sentry.io (l'IP del
  visitatore). Progetto Sentry del browser separato (`SENTRY_BROWSER_DSN`).

## 25. Consumatori (`docs/CONSUMATORI.md`)

- Il pulsante d'ordine dice **«Ordine con obbligo di pagamento»**
  (`PulsanteOrdine.vue`, shop e aste).
- Condizioni di vendita: testi in `database/data/condizioni_di_vendita.php`;
  ogni ordine porta `condizioni_versione` e lo sha256 del testo pubblicato
  (`condizioni_impronta`, testo in `versioni_condizioni`, mai cancellate); il PDF
  allegato si genera da lì. Il partial `emails/partials/informazioni-contrattuali`
  non si toglie.
- **Recesso online** su `/recesso` (`/en/withdrawal`, link via `route('recesso')`):
  due passaggi, ricevuta sincrona, righe mai cancellate dal pannello (10 anni se
  legate a un ordine, 12 mesi senza). Footer: il link resta.
- Newsletter con doppio opt-in (`confermato_il`). Revoca del pixel tutta in
  ActiveCampaign (Preference Center): niente pagina preferenze né tag sul sito.
- `AvvisoGaranziaLegale.vue`: immagini ufficiali UE, non ridisegnarle.
- Cancellazione ed export dati da `/shop/account` (ordini con `user_id` nullo).

## 26. Mappa delle dipendenze

`php scripts/mappa-dipendenze.php` (in CI dopo PHPStan) fallisce su codice
irraggiungibile da rotte/config/bootstrap/migrazioni/seeder e su riferimenti
inesistenti. Falsi positivi in `ECCEZIONI` col motivo; una nuova convenzione del
framework si insegna allo script, non si silenzia. `--html=mappa.html` per la mappa 3D.

## 27. Homepage

- **Match Day** (`App\Support\MatchDay`, gruppo `home`): `auto` / `off` / `on`
  (valido solo il giorno in cui si sceglie). Pop-up e «Biglietti» solo per gare
  in casa e prima del fischio d'inizio; il pop-up aspetta il banner cookie.
  Conto alla rovescia al minuto (`conteggioAllaPartita.js`, WCAG 2.2.2).
- **Striscia sponsor** a colori, ferma col pulsante di pausa; sotto sei non scorre.
- **Eventi** (`App\Models\Evento`): i prossimi tre pubblicati e non finiti.
- Test: `HomepageGoLiveTest`, `EventiEMatchDayNelPannelloTest`,
  `componentiDellaHome.test.js`.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scrolling (merging props + `WhenVisible`), lazy loading on scroll, polling, prefetching.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

=== filament/filament/core rules ===

## Filament

- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan

- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features

- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships

- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>

## Testing

- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>

## Version 3 Changes To Focus On

- Resources are located in `app/Filament/Resources/` directory.
- Resource pages (List, Create, Edit) are auto-generated within the resource's directory - e.g., `app/Filament/Resources/PostResource/Pages/`.
- Forms use the `Forms\Components` namespace for form fields.
- Tables use the `Tables\Columns` namespace for table columns.
- A new `Filament\Forms\Components\RichEditor` component is available.
- Form and table schemas now use fluent method chaining.
- Added `php artisan filament:optimize` command for production optimization.
- Requires implementing `FilamentUser` contract for production access control.

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>
