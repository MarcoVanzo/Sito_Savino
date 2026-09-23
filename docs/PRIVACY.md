# Privacy: la mappa tecnica

L'informativa pubblica racconta al visitatore quello che il sito fa. Questo file
dice **dove**, nel codice e nel database, quelle frasi diventano vere: serve a
chi tocca il sito, perché la volta che si aggiunge un trattamento e ci si
dimentica del testo pubblico, l'informativa comincia a dire il falso senza che
niente si rompa.

Non è un parere legale, ed è quello che va fatto leggere a chi segue la privacy
della società. I punti che aspettano una decisione non tecnica stanno in fondo.

Ultima verifica sul codice: **23 settembre 2026**.

---

## 1. Dove stanno i testi, e come si correggono

| Cosa | Dove |
| --- | --- |
| Privacy Policy e Cookie Policy (it + en) | `database/data/informative_privacy.php` |
| Lettura unica di quel file | `App\Support\TestiDelleInformative` |
| Pagine pubbliche | `pages` con slug `privacy-policy` e `cookie-policy`, template `Public/ContentPage` |
| Elenco dei cookie in fondo alla Cookie Policy | `database/data/cookie_rilevati.json` (scansione) descritto da `database/data/catalogo_cookie.json` |
| Versione dell'informativa a cui si lega il consenso | `App\Models\ConsensoCookie::VERSIONE` |

**I testi in produzione si correggono con una migrazione a guardie**, mai con un
`update` diretto: la redazione può averli già riscritti dal pannello, e in quel
caso la sua versione vince. La guardia è una delle `firme` del file dati — una
frase che solo una versione precedente conteneva.

Le `firme` sono **cumulative**: pubblicando una revisione si aggiunge la frase
della versione che se ne va e non si toglie niente, altrimenti una migrazione
vecchia che gira su un database nuovo non riconoscerebbe più niente da
correggere. Esempi: `2026_09_22_120000_le_informative_dicono_quello_che_il_sito_fa`,
`2026_09_23_110000_le_informative_dicono_anche_dei_volti`.

Il testo non si duplica altrove. `PageSeeder` e `database/data/content_translations_en.php`
ne tenevano una copia ferma al 2025 — quella che diceva "esclusivamente cookie
tecnici" mentre il sito caricava Google Analytics, con un indirizzo sbagliato e
un indirizzo email che non esiste: ogni ambiente nuovo, e il database dei test,
nascevano con quella. Ora il seeder legge `TestiDelleInformative::contenuto()`.

**Quando alzare `ConsensoCookie::VERSIONE`**: quando cambia *ciò che si
dichiara* — un tracker nuovo, una finalità diversa, un destinatario in più — non
a ogni ritocco di stile. Alzarla fa ricomparire il banner a tutti.

---

## 2. Che cosa il sito tratta davvero

| Dato | Dove finisce | Chi lo scrive | Per quanto, e chi lo applica |
| --- | --- | --- | --- |
| IP, browser, pagina | log del server; `sessions.ip_address`, `sessions.user_agent` | Laravel | sessione: 2 h di inattività (`SESSION_LIFETIME`) |
| Messaggi del modulo contatti | `contact_messages` | `ContactRequest` | 24 mesi dalla data del messaggio (`messaggi:pota`, settimanale) |
| Accrediti stampa (nome, telefono, testata, ruolo, gara) | `contact_messages` + `extra_data` | `PressAccreditationRequest` | idem, riga intera |
| Iscritti alla newsletter (email, nome) | `newsletter_subscribers`, poi ActiveCampaign | `NewsletterRequest`, `SyncNewsletterToActiveCampaign` | fino alla disiscrizione |
| Ordini: nome, indirizzi, telefono, codice fiscale | `orders`, `order_items` | `StoreCheckoutRequest` | 10 anni (obbligo fiscale) |
| Offerte d'asta | `bids` | `BidService` | con l'asta; in pagina il nome esce abbreviato (`AuctionService::maskUsername`) |
| Account dello shop | `users`, `password_histories` | registrazione | finché attivo |
| Carrelli | `carts`, `cart_items` | | 7 giorni (`carts:prune-expired`, `Cart::prunable`) |
| Prova del consenso ai cookie | `consensi_cookie` | `ConsensoCookieController` | 12 mesi (`consensi:pota`, settimanale) |
| Chi ha modificato cosa nel pannello | `activity_logs` | `LogsActivity` | 180 giorni (`activity-log:prune`) |
| Fotografie e persone ritratte | `media`, `gallery_images`, `gallery_image_person` | redazione + `AnalyzeGalleryImageJob` | finché la foto resta pubblicata |
| Impronte dei volti di atlete e staff | **fuori dal database**: nel Postgres di CompreFace | pannello → `FacialRecognitionService::addFaceExample` | finché il consenso regge; alla revoca si cancella il soggetto |

**Nel registro dei consensi non c'è l'indirizzo IP**, solo
`hash('sha256', $ip.'|'.config('app.key'))` (`ConsensoCookie::improntaDi`): senza
`APP_KEY`, che non lascia il server, non si torna indietro. È una prova di
consenso (art. 7 §1), non un registro statistico: non aggiungerci altro.

---

## 3. Chi riceve i dati, e dove si vede nel codice

| Destinatario | Cosa riceve | Dove |
| --- | --- | --- |
| DigitalOcean | hosting, database, file (Spaces, `fra1`) | `.do/app.yaml` |
| PayPal | pagamenti | `config/services.php` → `paypal` |
| Stripe | pagamenti, **quando ha le chiavi** — in produzione non le ha, e senza credenziali il metodo non viene nemmeno offerto (`PaymentGateway::configurato()`) | `services.stripe` |
| Resend | email di servizio, dal go-live | `services.resend`, `MAIL_MAILER` |
| ActiveCampaign | newsletter | `services.activecampaign` |
| Google Ireland | GA4, **solo dopo il consenso statistico** | `resources/js/analytics.js` |
| Meta Platforms Ireland | pixel, **solo dopo il consenso marketing** (`META_PIXEL_REQUIRES_CONSENT`, predefinito `true`) | `resources/js/meta-pixel.js` |
| Google (Maps), YouTube, Vimeo | l'IP di chi apre una pagina con la mappa o un video, **senza consenso** (§5) | `Societa/Palazzetto.vue`, `LiveStreamModal.vue`, `PageMediaTail.vue` |

**I caratteri tipografici non sono più fra questi.** Montserrat e Playfair
Display arrivavano da `fonts.googleapis.com` su ogni pagina pubblica: l'IP del
visitatore raggiungeva Google prima di qualsiasi scelta, per una cosa che
possiamo servire noi. Ora stanno in `public/fonts`, i `@font-face` in
`resources/css/app.css`, e la CSP non ha più bisogno degli host di Google.

**CompreFace non è una terza parte**: gira su un droplet della società, a
Francoforte, raggiungibile solo dalla VPC (`COMPREFACE_HOST`). Le fotografie non
escono verso servizi di riconoscimento esterni.

---

## 4. Riconoscimento dei volti, in breve

Quello che l'informativa dice, e dove sta:

- ogni foto dell'archivio passa da `AnalyzeGalleryImageJob` → `/recognize` di
  CompreFace, che **rileva tutti i volti nell'immagine** — atlete, staff, e
  chiunque altro ci sia dentro — e li confronta con i soggetti registrati;
- **si conservano solo le impronte dei soggetti registrati** dalla redazione
  (`addFaceExample`, atlete e staff). Il confronto degli altri volti avviene in
  memoria e non lascia niente: nessun profilo del pubblico;
- i nomi riconosciuti finiscono sul pivot `gallery_image_person`, nel filtro per
  persona dell'archivio e **nel titolo e nella descrizione dell'immagine**
  (`optimizeForSeo`), che è ciò che indicizzano i motori di ricerca — è la parte
  che rende il nome trovabile da fuori, e il motivo per cui l'informativa la
  dice;
- il job **non toglie mai un tag** (`updateOrInsert`): per disfare
  un'attribuzione sbagliata si cancellano le righe con `confidence_score` non
  nullo e si azzera `ai_analyzed_at` (vedi `CLAUDE.md` §12-ter);
- niente decisione automatizzata ai sensi dell'art. 22: l'associazione è una
  proposta che la redazione può togliere;
- la **base giuridica dichiarata è il consenso esplicito** dell'interessato
  (art. 9 §2 lett. a), raccolto dalla società fuori dal sito. Alla revoca vanno
  cancellati il soggetto su CompreFace (`deleteAllSubjectExamples`) e le righe
  di `gallery_image_person` con `confidence_score` non nullo: le fotografie
  restano, il nome no.

Chiede di comparire o di sparire: `info@savinodelbenevolley.it`, come tutti gli
altri diritti.

---

## 5. Punti aperti

Due, e nessuno dei due si chiude scrivendo codice da soli. Il terzo — i
ventiquattro mesi dei messaggi che nessun comando applicava — è chiuso:
`messaggi:pota` gira ogni settimana.

1. **Il consenso al riconoscimento dei volti va raccolto davvero, e nessuno lo
   verifica.** L'informativa dichiara la base giuridica — consenso esplicito,
   art. 9 §2 lett. a — ma il pannello lascia addestrare **qualunque** atleta in
   rosa, comprese le U15 e le U17, e nessun campo dice se quel consenso esista:
   `players.ai_face_examples` conta le foto imparate, non l'accordo della
   persona (e su `staff_members` la colonna non c'è proprio). Finché la raccolta
   vive su carta, la promessa la mantiene chi usa il pannello.
   Se un giorno la si vuole rendere verificabile: una data
   `consenso_biometrico_il` sui due modelli, e l'azione di addestramento
   disabilitata finché è vuota.
2. **Mappa e video si caricano prima del consenso.** Gli iframe di Google Maps,
   YouTube e Vimeo partono con la pagina: la Cookie Policy ora lo dice, ma
   dirlo non lo rende lecito. È una scelta presa sapendolo (23/09/2026), non una
   dimenticanza. Il rimedio, quando si vorrà, è il *click-to-load* — un riquadro
   al posto dell'iframe finché il visitatore non chiede di vederlo — nei tre
   componenti elencati al §3. Fino ad allora la scansione settimanale dei cookie
   può trovare cookie di terze parti prima della scelta: è un difetto vero, non
   un falso positivo, e non va silenziato.

## 6. Quando si aggiunge un trattamento

Quattro cose, nell'ordine:

1. il dato nuovo va nella tabella del §2 di questo file, con la sua
   conservazione e **il comando che la applica** (se non c'è, scriverlo);
2. l'elenco "quali dati raccogliamo" dell'informativa va aggiornato, con la base
   giuridica;
3. se entra un fornitore, va nei destinatari e — se è extra-UE — nella sezione
   dei trasferimenti;
4. se cambia quello che si dichiara al visitatore sui cookie, si alza
   `ConsensoCookie::VERSIONE` e il banner torna a chiedere.

Un cookie nuovo si spiega in `database/data/catalogo_cookie.json`, non
nell'editor del pannello: finché non è lì, la dichiarazione lo pubblica come
"non ancora classificato", ed è voluto.
