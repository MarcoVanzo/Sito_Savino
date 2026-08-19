# Analytics — sito, social, newsletter

Tre pagine del pannello, sotto **Comunicazione** (Analytics Sito, Social
Analytics) e **Marketing** (Analytics Newsletter). Chi le vede: ruoli con
`canManageEditorial()` — Super Admin e Gestione Comunicazione.

---

## 1. Cosa serve per farle funzionare

Le credenziali **non stanno nel repository** (che è pubblico): vivono nelle
variabili d'ambiente locali e nei secret cifrati di DigitalOcean App Platform.
Le voci sono già in `.env.example`.

### Google Analytics 4

| Cosa | Dove |
|---|---|
| Proprietà GA4 + flusso di dati Web | Da creare su analytics.google.com. Fuso `Europe/Rome`. |
| **Measurement ID** (`G-XXXXXXXXXX`) | Pannello → Impostazioni → Analytics. **Non** in `.env`: cambia senza rilascio. |
| **Property ID** (numerico) | Pannello → Amministrazione → Siti Analytics, un record per sito. |
| **Service account** | Google Cloud: abilitare *Google Analytics Data API*, creare il service account, scaricare il JSON → `GA4_SERVICE_ACCOUNT_JSON` (anche base64) o `GA4_SERVICE_ACCOUNT_FILE`. |
| Autorizzazione | L'email del service account va aggiunta come **Visualizzatore** su ogni proprietà (GA4 → Amministrazione → Gestione accessi). L'indirizzo è scritto in Impostazioni → Analytics. |

Verifica: Amministrazione → Siti Analytics → **Verifica accesso** sulla riga del
sito. Risponde con gli utenti degli ultimi 7 giorni o con il motivo esatto del
rifiuto.

### Meta (Instagram + Facebook)

| Cosa | Dove |
|---|---|
| App Meta | Creata **dentro il portfolio business che possiede le Pagine**. Così basta lo Standard Access e non serve l'App Review sui permessi di insight. In produzione è `Savino Analytics`, collegata al portfolio *Savino Del Bene Volley* (proprietario della Pagina `Savino Del Bene Volley Scandicci` e del profilo `@savinodelbenevolley`). |
| `META_APP_ID`, `META_APP_SECRET` | Impostazioni di base dell'app. `META_APP_ID` = `1098244582719500`; il segreto sta cifrato nello spec, mai in chiaro. |
| `META_CONFIG_ID` | ID della configurazione di *Facebook Login for Business*. In produzione `1530681078385238` (configurazione `Sito Savino Insights`, token d'accesso **dell'utente**: il codice fa `fb_exchange_token` e poi `/me/accounts`, che con un token di utente di sistema non funzionerebbe). |
| URI di reindirizzamento | `https://<dominio>/admin/social/meta/callback` — lo stampa Impostazioni → Analytics, va copiato identico nell'app Meta. |

Permessi della configurazione: `instagram_basic`, `instagram_manage_insights`,
`pages_show_list`, `pages_read_engagement`, **`read_insights`**,
`business_management`.

I permessi non compaiono nella tendina della configurazione finché non sono
stati aggiunti ai **casi d'uso** dell'app (*Casi d'uso → Personalizza →
Autorizzazioni e funzioni*): `read_insights` e `pages_read_engagement` stanno
sotto *Gestisci tutto sulla tua Pagina*, `instagram_basic` e
`instagram_manage_insights` sotto *Gestisci i messaggi e i contenuti su
Instagram*.

> `read_insights` è quello che si dimentica. Senza, la Graph API **non dà
> errore**: risponde con metriche vuote. La pagina lo riconosce (tutte le
> metriche accettate tornate a zero) e lo dice in chiaro, ma la connessione va
> comunque rifatta.

Il collegamento si fa da **Social Analytics → Collega Meta**. Un solo giro OAuth
porta dentro *tutte* le Pagine amministrate dal profilo: prima squadra e settore
giovanile diventano due righe distinte in `social_accounts`. Ricollegare
aggiorna le righe esistenti e non tocca la serie storica.

### Meta Pixel (pubblicità)

Cosa diversa dalla Graph API: la Graph API **legge** gli insight dei profili, il
Pixel **misura il sito** per attribuire le conversioni alle inserzioni e
costruire i pubblici di retargeting. Non è legato a un account IG o FB: è uno
per sito, appeso al portfolio business, e lo usano le campagne di entrambi.

L'ID si imposta dal pannello (*Impostazioni → Analytics*), non da `.env`: il
browser lo espone comunque in chiaro e cambia senza bisogno di un rilascio.

Eventi inviati: `PageView` a ogni navigazione, e il funnel dello shop —
`ViewContent` (scheda prodotto), `AddToCart`, `InitiateCheckout`, `Purchase`
(con valore, valuta e contenuti).

> `Purchase` è bloccato per numero d'ordine su `sessionStorage`. La pagina di
> conferma si ricarica da sola ogni 5 secondi finché il webhook del pagamento
> non arriva: senza il blocco, un ordine solo verrebbe contato fino a dodici
> volte e il ritorno delle campagne risulterebbe gonfiato.

**Consenso**: oggi il pixel si carica per tutti, scelta di configurazione
esplicita. `META_PIXEL_REQUIRES_CONSENT=true` lo subordina al toggle "marketing"
del banner cookie; il codice per farlo è già collegato, cambia solo la variabile.
Finché resta `false`, quel toggle nel banner non governa nulla.

### ActiveCampaign

Già configurato per le iscrizioni (`ACTIVECAMPAIGN_URL`, `_API_KEY`, `_LIST_ID`).
La pagina Newsletter riusa le stesse credenziali in sola lettura.

---

## 2. Come sono fatte

### Analytics Sito

`App\Services\Analytics\` — quattro classi con responsabilità separate:

- `Ga4Credentials` — service account → JWT RS256 → access token (in cache ~55').
  Si è scelto il service account e non l'OAuth per utente per non passare dalla
  verifica Google dello scope sensibile `analytics.readonly`.
- `Ga4Client` — le tre chiamate alla Data API. Restituisce righe già appiattite.
- `Ga4ReportAssembler` — **puro**: costruisce gli otto report e traduce le
  risposte. È la parte che si rompe se Google rinomina qualcosa, ed è coperta
  da test senza rete.
- `WebAnalyticsService` — cache, archivio, degrado.

Gli otto report: totali corrente+precedente, serie giornaliera, **pagine più
viste** (`pagePath` + `pageTitle`), canali, sorgenti, dispositivi, città, pagine
di ingresso. Il batch di Google ne accetta cinque per volta ⇒ due chiamate.

`web_analytics_daily` conserva la serie a ogni lettura. Serve a due cose: i
confronti anno su anno restano anche se la proprietà GA4 viene sostituita, e
quando Google non risponde la pagina mostra i numeri salvati marcati come
`degraded` invece di restare bianca. `is_final` distingue i giorni che GA4 non
rielabora più (48 ore).

**La misurazione sul sito pubblico** sta in `resources/js/analytics.js`:
Consent Mode v2 con tutto negato di partenza, tag caricato solo dopo il consenso
sui cookie di statistica, e un `page_view` a ogni navigazione Inertia — senza
quello, in una SPA tutto il traffico risulterebbe sulla pagina d'ingresso e non
esisterebbe nessun "pagina per pagina".

### Social Analytics

`App\Services\Social\`:

- `MetaClient` — trasporto; `MetaException` — traduzione degli errori in cause
  su cui si decide (solo `invalid_metric` merita un secondo tentativo).
- `MetaOAuthService` — giro OAuth, state a scadenza usa-e-getta, salvataggio
  degli account. Il token è cifrato a riposo (cast `encrypted`).
- `InstagramInsights` — profilo, totali, ripartizioni, demografia, contenuti.
- `FacebookPageInsights` — metriche della Pagina, una chiamata per metrica.
- `InstagramDailySync` — la serie giorno per giorno.
- `SocialAnalyticsService` — mette insieme tutto per la pagina.

**Perché una chiamata per giorno.** La Graph API dà le metriche di account in
due forme incompatibili: `time_series` (solo `reach` e `follower_count`, un
valore al giorno) e `total_value` (tutto il resto, **un numero** per l'intero
intervallo). L'unico modo di sapere quanto ha fatto martedì è chiedere
l'intervallo "solo martedì". Si scarica una volta sola e si conserva in
`social_insights_daily`; un giorno `is_final` non si richiede mai più.

Da qui il tetto alle chiamate: **15** aprendo la pagina (Meta concede ~200
richieste l'ora), **120** nel comando notturno.

I totali di periodo **non** sono la somma dei giorni: per reach e account
raggiunti sommare i giorni conterebbe più volte la stessa persona. Si usa
`total_value` sull'intero intervallo, che è il numero che Meta considera valido.

Una Pagina senza Instagram si collega comunque: restano le metriche Facebook.

### Newsletter

`App\Services\Newsletter\` — due metà tenute separate apposta: gli **iscritti**
vengono dalla nostra tabella (fonte certa, è da lì che parte la
sincronizzazione), le **campagne** da ActiveCampaign. Se ActiveCampaign non
risponde la prima metà resta. Le medie di apertura e click sono pesate sugli
invii: una campagna di prova da 10 destinatari non deve spostare la media di una
da 5.000.

`ActiveCampaignService` (scrittura: iscrizioni, disiscrizioni) resta separato da
`ActiveCampaignReports` (lettura): il primo sta nel percorso di un'iscrizione dal
sito e non deve crescere con codice che serve solo al pannello.

---

## 3. Comandi schedulati

| Comando | Quando | Cosa fa |
|---|---|---|
| `social:sync-meta --days=90` | 03:30 | Riempie la serie Instagram, fino a 120 giorni per giro. |
| `analytics:sync-ga4 --days=90` | 05:00 | Porta in archivio la serie GA4 anche se nessuno apre il pannello. |

Entrambi escono con successo quando non c'è niente da fare: un fallimento
quotidiano diventerebbe rumore da ignorare.

---

## 4. Cose da sapere prima di metterci mano

- **Lo storico GA4 non esiste prima del tag.** Il traffico si misura dal giorno
  in cui il Measurement ID viene inserito: non è recuperabile a posteriori.
- **GA4 misura solo chi accetta i cookie.** I numeri sono strutturalmente più
  bassi del traffico reale (tipicamente 60–80%).
- **Meta consolida con due giorni di ritardo.** Prima di 48 ore un giorno può
  ancora cambiare: è il motivo di `is_final`.
- **Meta ritira metriche a ondate.** `page_impressions` è già stata sostituita
  da `page_media_view`. Ogni metrica Pagina è una chiamata a sé proprio perché
  una morta non azzeri le altre.
- **La demografia Instagram esiste solo sopra i 100 follower** e solo per i
  primi 45 valori di ogni voce.
- **Scollegare non cancella.** Il pulsante azzera il token e lascia la riga: la
  serie storica scaricata non si potrebbe riscaricare.

## 5. Test

- `tests/Unit/Analytics/` — assemblatore GA4, traduzione degli errori, credenziali.
- `tests/Feature/Analytics/` — archivio, degrado, cache.
- `tests/Unit/Social/`, `tests/Feature/Social/` — classificazione errori Meta,
  budget di chiamate, giro OAuth (state usa-e-getta, token cifrato).
- `tests/Feature/Newsletter/` — medie pesate, tenuta senza ActiveCampaign.
- `tests/Feature/Filament/AnalyticsPagesTest.php` — permessi e apertura delle
  pagine con i servizi esterni assenti o guasti.
- `resources/js/analytics.test.js` — il consenso ha un effetto reale.
- `resources/js/meta-pixel.test.js` — deduplica di `Purchase` e regola sul consenso.
