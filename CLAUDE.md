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
  `public:gallery_images:<locale>`. Le varianti per atleta si buttano.

## 12-ter. Riconoscimento dei volti (CompreFace)

- **CompreFace** su un droplet raggiungibile **solo dalla VPC**
  (`COMPREFACE_HOST`); chiave vera nel suo Postgres. Le foto di addestramento
  non restano né nel repo né su Spaces.
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
- `CmsPagesSeeder` **non si rilancia in produzione**.
- Revisione del lavoro della redazione: `activity_logs`. Correzioni ai testi in
  produzione con **migrazione a guardie** (tocca solo il valore ancora
  sbagliato), provata a secco su una copia delle righe.

### Impostazioni (`SiteSetting`)
- `get()` accetta `chiave` e `gruppo.chiave` (prima la chiave letterale, poi il
  gruppo). **Mai creare in una migrazione la forma `gruppo.chiave`**: oscura il
  valore salvato nel gruppo.
- Chiavi nuove di un gruppo: con una migrazione (`set()` le mette nel gruppo
  predefinito). Le `json` si traducono come le altre (`resolveForLocale()`).
- Recapiti e dati societari: gruppo `contact`, non `content_data`.

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
- Squadre del vivaio per `teams.category` (`B1`, `U17`, `U15`), non per slug.
- Template specifici: Ticketing (`TicketingTemplateForm`, blocchi facoltativi,
  la biglietteria non ha listino), Convenzioni (`partners`), Affiliazioni
  (`affiliates`, `AffiliateTier`, import `affiliazioni:importa-dal-vecchio-sito`),
  Club Race (`standings`), Safeguarding (documenti per chiave da Documenti
  Legali, `DocumentiLegali::risolviNeiDocumenti`). Sezioni nuove nascono
  facoltative; il testo dell'editor è l'introduzione sotto l'hero.
- Ordine categorie news: `categories.sort_order`, non riordinare nel frontend.
- `/stagione/risultati?squadra=savino` apre il calendario filtrato.
- `players.instagram_handle`: nome utente, esposto da `Player::instagramUrl()`.
- Mappa del Palazzetto: `content_data.maps_iframe_src` (solo il `src` Google).

### Notizie e vecchio sito
- Il vecchio WordPress non esiste più. L'import da `wp-json`
  (`news:importa-dal-vecchio-sito`) ha fatto l'ultimo giro prima del DNS; lo
  scheduler si spegne da sé il 2/10/2026 (`services.vecchio_sito.leggibile_fino_a`,
  test `ImportNotizieSchedulatoTest`).
- Chiave naturale di una notizia: `wp_id`, poi slug; categorie per `wp_id`, poi
  slug, poi nome esatto (e adottano il `wp_id`).
- Date dei comunicati = ora locale; il connettore MCP le mostra spostate:
  leggere con `CAST(published_at AS CHAR)`.
- Slug tipo `41541-2` non sono slug: l'import li rigenera dal titolo.
- I media delle notizie stanno su Spaces sotto `news/<anno>/<mese>/`
  (`MediaDelVecchioSito`).

## 15. Sponsor

- Livelli in `App\Enums\SponsorTier` (ordine dei case = ordine in pagina);
  raggruppamento in `App\Services\SponsorDirectory`, condiviso. **Mai rilanciare
  `sponsors:import-legacy` in produzione**: l'elenco lo cura la redazione.

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
  (`AuctionCheckoutController::terminePerIlBonifico`). Un pagamento arrivato con
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
- **Ogni `throttle:N,M` ha il terzo parametro** (nome del limite), o i limiti
  condividono il contatore (`LimitiDelleRotteSeparatiTest`).

## 19. Anteprime social

`ServeSocialCrawlerMeta` risponde ai crawler (`CRAWLER_PATTERNS`) con un HTML
minimale; i meta `og:` di `app.blade.php` sono statici.
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
  zona. Gli interruttori di shop e aste li decide la redazione, non una migrazione.
- `Auction::status` si cambia solo con `Auction::cambiaStato()` (`TRANSIZIONI_AMMESSE`).
  Il prodotto di un'asta esce e rientra dallo shop via `AuctionObserver`.
- Giacenza con varianti = somma delle taglie (`Product::availableStock`), non
  `products.stock`.
- **Prezzo barrato** = il più basso dei 30 giorni prima dello sconto
  (`StoricoPrezzi`, art. 17-bis); senza storico non si barra. Lo sconto si
  annuncia solo mentre è in corso (`effectivePrice()`).
- **Etichette** (`products.etichette`, `EtichetteDelProdotto`): IN OFFERTA e
  ULTIMO RIMASTO solo se vere (Codice del consumo, artt. 21 e 23).
- Coupon su prodotti/categorie (`Coupon::valePerIlProdotto`): sconto sulla parte
  ammessa, ordine minimo sulla spesa intera. Il coupon sconta anche la firma (scelta).
- **Spedizione**: fasce in `shipping_zones.weight_rates`; soglia gratuita prima
  delle fasce; peso via `Product::pesoPerLaSpedizione()` (ripiego
  `shop.default_item_weight_kg`). **Conto scritto due volte**:
  `ShippingZone::calculateShippingCost` e `resources/js/Support/spedizione.js`
  (unico per shop e aste): si cambiano insieme.
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
  `APP_KEY`, 12 mesi (`consensi:pota`).
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
  fuori dal sito). Alla revoca: cancellare il soggetto su CompreFace e le righe
  AI di `gallery_image_person`.

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
