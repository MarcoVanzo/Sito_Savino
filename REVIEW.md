# Revisione pre-consegna — sito Savino Del Bene Volley

Branch `review/pre-consegna` (da `main` @ `4d6bc37`), 27/09/2026. Consegna: giovedì 1/10/2026.
Sito esaminato: `https://seashell-app-47mmf.ondigitalocean.app` (produzione, solo GET) + codice + DB di produzione in sola lettura (`readonly_savino`).

## Verdetto: **NO-GO sullo stato attuale → GO dopo 3 azioni (≈ 3 h)**

1. **La `APP_KEY` di produzione è pubblica** nella history del repo GitHub pubblico (S1, P0): va ruotata prima della consegna. Solo Marco può farlo (tocca la produzione).
2. **`robots.txt` vieta `/build/`** (SEO-1, P0): senza SSR Google indicizzerebbe pagine vuote dal 1/10. Corretto sul branch: va mergiato e deployato.
3. Il resto è in ordine: build, 1.618 test PHP, 307 test JS, PHPStan, audit delle dipendenze e header di sicurezza sono verdi; non ci sono overflow su 4 larghezze × 2 motori e nessun tracker parte prima del consenso. Gli altri P1 del codice sono corretti sul branch; restano **contenuti** (redazione) e il **perimetro rispetto alla Proposta** (da concordare col cliente).

---

## Tabella dei problemi

Legenda. Priorità: **P0** blocca la consegna, **P1** va fatto prima di giovedì, **P2** dopo la consegna. Stato: **verificato** = riprodotto o provato; **sospetto** = indizio senza prova piena.
«Corretto (commit)» significa corretto sul branch e non ancora in produzione.

| ID | P | Area | Dove | Prova | Impatto | Soluzione | Ore | Stato |
|---|---|---|---|---|---|---|---|---|
| S1 | **P0** | Sicurezza | `.do/app.yaml` nei commit `006a10d`, `9396109`, `057d316`, `58fd24e` (01–02/07) | La chiave `base64:F/Qm8y…` presa dalla history ricalcola il MAC del cookie `XSRF-TOKEN` emesso oggi da `/csrf-cookie`: **CORRISPONDE** (verificato due volte, in modo indipendente) | Il repo è pubblico: chiunque può forgiare URL firmati (`/newsletter/disiscriviti/{id}` mostra l'email → export dell'intera lista; `/recesso/ricevuta/{id}` → dati dei recessi; `verify-email`), forgiare cookie e snapshot Livewire, e decifrare `social_accounts.access_token` | Nuova chiave cifrata in `.do/app.yaml` su **web, worker e scheduler**; la vecchia in `APP_PREVIOUS_KEYS` solo il tempo di ricifrare i token Meta. Sessioni e link firmati già spediti decadono. Non riscrivere la history. | 2 | verificato — **da fare (Marco)** |
| SEO-1 | **P0** | SEO | `public/robots.txt:12` | `Disallow: /build/`; con UA Googlebot l'HTML di una notizia ha il titolo generico e nessun testo (`<div id="app" data-page>`) | Dal 1/10 Google vede ~2.000 pagine vuote e con lo stesso titolo, e i 301 dal WordPress finiscono lì | Tolta la riga, con un test di regressione | 0,25 | **corretto** `0eeb4f5` |
| C1 | P1 | Shop | `app/Console/Commands/CheckUnpaidOrders.php:27-35`, `routes/console.php:119` | Test: ordine con bonifico di 6 giorni, 2 giri a 10 minuti di distanza → 2 promemoria. In produzione 0 ordini finora | Circa 288 email identiche per ogni cliente che sceglie il bonifico e non paga (la posta esce dal 25/09) | Segno «già inviato» nello store `persistente` con `add()` atomico; si ritenta solo se l'invio fallisce | 1 | **corretto** `2561791` |
| SEO-2 | P1 | SEO | `public/.htaccess:16-19` | `curl -sI …/contatti/` → `location: http://…` (3 salti, uno in chiaro) | Tutti i permalink WordPress (terminano con `/`) passano per http | Con `X-Forwarded-Proto: https` il redirect va direttamente in https. Provato su Apache locale | 0,5 | **corretto** `4c77b83` |
| C3 | P1 | Moduli | `app/Http/Controllers/ContactController.php:48` | `->to(config('mail.from.address'))` = `noreply@savinodelbenevolley.it` (`.do/app.yaml:39-41`); in produzione `contact.email` = `info@` | Le notifiche del modulo contatti vanno a una casella che nessuno legge (il messaggio resta solo nel pannello) | Destinatario `contact.email`, mittente come ripiego. Test sul destinatario reale | 0,5 | **corretto** `6adc2a6` |
| R4 | P1 | Lancio | `app/Console/Commands/VerificaIlLancio.php:213` | Il comando dice «Cambiando dominio va rifatto» il webhook PayPal | Contraddice GO_LIVE §3: un webhook nuovo lascia gli ordini «pending» | Testo corretto («si modifica, non si ricrea») | 0,25 | **corretto** `a3cb6b1` |
| PERF-1 | P1 | Performance | `resources/js/Pages/Public/Home.vue:486-506` | Lighthouse mobile home: LCP **20,4 s**, 4,1 MB; 8 immagini hero da 270–450 KB scaricate subito come `background-image` di slide invisibili | Primo impatto lento su telefono proprio nella pagina più visitata | Lo sfondo lo ha solo la slide in scena più la successiva. Verificato sul DOM: all'avvio 1 immagine + 1 sfondo, poi una slide alla volta | 1 | **corretto** `3882d86` |
| R8 | P1 | Disponibilità | `app/Providers/AppServiceProvider.php:227-230` | `throttle:web` = 60 richieste/min **per IP** su tutto il sito; durante la revisione il mio IP ha ricevuto 429 | Wi-Fi del palazzetto (prima gara in casa il 4/10), CGNAT mobile e scuole condividono lo stesso contatore: ~50 tifosi bastano ad avere 429 | Letture anonime a 300/min su un contatore dedicato; le scritture restano a 60 (e ognuna ha già il suo limite) | 0,5 | **corretto** `4436f57` |
| S3 | P1 | Riservatezza | `Proposta_Tecnica_SDB_v3.4.pdf` | Tracciato nel repo pubblico; il PDF porta «PROPOSTA TECNICA RISERVATA — Uso riservato» e contiene i costi | Documento commerciale del cliente esposto | `git rm --cached` + `.gitignore` (approvato). Copia salvata in `~/Sviluppo/Savino/`. **Resta nella history**: solo rendendo privato il repo | 0,1 | **corretto** `140781a` |
| A11Y-1 | P1 | Accessibilità | `resources/js/Pages/Public/Risultati.vue:362` | axe `color-contrast` serious, 182 nodi a 375 px: `text-white/50` su `#003063` = 4,39:1 | WCAG 2.1 AA non rispettato (EAA) | `text-white/70` = 7,1:1 (approvato) | 0,1 | **corretto** `37c65e1` |
| A11Y-2 | P1 | Accessibilità | Risultati, Classifica, `DichiarazioneCookie.vue`, `SeasonStatsTable.vue`, `Partita.vue` | axe `scrollable-region-focusable` serious su risultati, classifica e cookie-policy a 375 px | Con la sola tastiera non si scorrono le tabelle | `tabindex=0` + `role=region` + etichetta (approvato). Axe locale: 0 violazioni | 0,3 | **corretto** `37c65e1` + `549f683` |
| S2 | P1 | Sicurezza | commit `4e2da64`, `3ac730e` (26/06), `.do/app.yaml` | Access key Spaces `DO002PZM…` e secret in chiaro nella history | Se ancora attiva, dà accesso in scrittura ai file del sito | `doctl spaces keys list --context savino` e, se compare, revocarla. Probabilmente è già revocata | 0,25 | sospetto — **da fare (Marco)** |
| R1 | P1 | Perimetro | Proposta Tecnica §05B/§06 | Nessuna traccia nel codice (grep) di: banner sponsor a scorrimento in home, banner Youth, spazio Eventi, Match Day mode, pop-up Vivaticket, countdown, Quick Edit/Quick Post/Live Preview, backup on-demand, export CSV, sync CEV, card XL della capitana, moduli nativi Progetto Scuola/Talent Day | Il cliente può contestare alla consegna (il banner sponsor è visibilità venduta ai partner) | Mandare **prima di giovedì** un elenco scritto delle differenze e concordare cosa è fuori perimetro o in una fase 2 | 1 (+ sviluppo) | verificata l'assenza; sospetto che parte sia stata concordata a voce |
| R2 | P1 | Contenuti | `/stagione/b1`, `/stagione/u17`, `/stagione/u15` | Props: rosa e staff vuoti; sono tre voci di menu | Pagine vuote raggiungibili dal menu | La redazione carica le rose, oppure si nascondono le voci dal pannello | 0,5–2 | verificato — redazione |
| SEO-3 / R5 | P1 | Contenuti shop | `/shop/guida-taglie` | `sizeGuides=[]`, `shop.size_guides="[]"` in produzione: la pagina dice «Guida taglie in arrivo» | Voce del menu Shop che porta a un segnaposto | Caricare i PDF Erreà in «Guida Taglie & Contatti», oppure nascondere la voce | 0,25 | verificato — redazione |
| SEO-4 | P1 | Contenuti shop | DB `products` | 10 prodotti attivi su 22 senza descrizione; 4 coppie home/away con lo stesso nome («MAGLIA GARA EZE #2», …); refuso «MAGLIA GARAVAN HECKE #7» | Home e away indistinguibili anche nell'ordine; mancano le caratteristiche principali (art. 49 Cod. consumo) | Redazione: nomi distinti, refuso, almeno una descrizione breve; se le maglie sono indossate o firmate, compilare lo «Stato dell'articolo» | 1–2 | verificato — redazione |
| R6 | P1 | Contenuti | `/comunicazione/magazine` | `content_data` = `{}`: la pagina promette «tutti i numeri» e non ha PDF | Pagina vuota nel menu | Caricare i PDF o nascondere la voce | 0,25 | verificato — redazione |
| SEO-5 | P1 | Shop | impostazione `shop.bank_transfer_beneficiary` | Valore: `"Pallavolo Scandicci Savino Del BeneSoc.  Sport. Dilett. A Resp. Li"` (manca uno spazio, uno doppio, troncato) | Nelle email del bonifico; con la verifica del beneficiario SEPA può dare «nome non corrispondente» | Farsi dare dall'amministrazione l'intestazione esatta del conto | 0,25 | sospetto — amministrazione |
| R12 | P1 | Lancio | variabile di repo `URL_SITO` | `gh variable list` vuoto; le scansioni settimanali ripiegano sull'host `ondigitalocean.app` | Da passo del giorno X: dopo il 1/10 le scansioni guarderebbero l'host sbagliato | `gh variable set URL_SITO --body https://savinodelbenevolley.it` il giorno del passaggio | 0,1 | verificato — giorno X |
| C2 | P2 | Shop | `app/Enums/PaymentGateway.php:46` | Il bonifico è «configurato» anche con l'IBAN vuoto (in produzione l'IBAN c'è) | Rischio solo se qualcuno svuota l'IBAN | `configurato()` richiede IBAN e beneficiario | 1 | verificato (non attivo) |
| C4 | P2 | Shop | `CheckoutController.php:123-161` | Se il gateway fallisce dopo `createOrder`, il cliente arriva su «carrello vuoto» | Nessun «riprova»; merce e coupon bloccati per un'ora | Nel `catch` rimandare a `shop.checkout.cancel` | 1 | verificato da codice |
| C5 | P2 | Shop | `CheckUnpaidOrders.php:29-30,50` | 5 e 7 giorni fissi; `shop.bank_transfer_expiry_days` (mostrato nelle email) viene ignorato | Email e annullo possono dire cose diverse | Usare l'impostazione | 0,5 | verificato |
| C6 | P2 | Shop | `CartController.php:78-83` | `variant_id` nullable lato server | Un client modificato aggiunge un prodotto con taglie senza taglia | Rifiutare se il prodotto ha varianti | 0,5 | sospetto |
| C7 | P2 | UX | `bootstrap/app.php:133-148` | `/aaa/bbb/ccc/ddd` → pagina 404 senza menu né footer, in italiano anche sotto `/en` | Vecchi permalink a più segmenti | `Route::fallback()` nel gruppo pubblico | 1 | verificato (test) |
| C9 / S4 | P2 | Pulizia | root del repo | Tracciati `Oops.rej`, `patch_cart_controller.patch`, `patch_checkout.patch`, `AUDIT_COMPARATIVO.md` (residui, senza dati sensibili) | Nessuno | `git rm` | 0,2 | verificato |
| S5 | P2 | Sicurezza | `/register` (Breeze) | Risponde 200, crea utenti `is_active=false`, throttle 5/min, nessun captcha | Account inattivi in massa, senza accesso al pannello | Chiudere la rotta se non serve | 0,5 | sospetto |
| S6 | P2 | Sicurezza | history | Chiave ActiveCampaign nella history (già accettata dal titolare il 18/09) | — | Ruotarla insieme a S1 | 0,25 | verificato |
| S7 | P2 | Sicurezza | `routes/auth.php:58,60` | `confirm-password` e `PUT password` senza throttle (richiedono login) | Basso | Aggiungere `throttle` con prefisso | 0,1 | verificato |
| S8 | P2 | Manutenzione | `composer audit` | `filament/spatie-laravel-translatable-plugin` abbandonato → `lara-zeus/spatie-translatable` | Nessun aggiornamento di sicurezza futuro | Migrare dopo la consegna (è legato al content driver di §9) | 3 | verificato |
| SEO-6 | P2 | SEO | `resources/js/Pages/Public/Shop/ProductDetail.vue:226` | JSON-LD `Product.url` = `/shop/{slug}` → HEAD 404; `description` in HTML grezzo | Dati strutturati non validi | `route('shop.product', slug)` + testo senza tag | 0,5 | verificato |
| SEO-7 | P2 | SEO | sitemap | 959 notizie senza traduzione pubblicate anche come `/en/news/…` con hreflang | ~960 duplicati | Togliere dalla sitemap le `/en/` senza traduzione, o canonical sull'italiano | 1,5 | verificato |
| SEO-8 | P2 | SEO | `/news?page=2` | canonical → `/news` | Pagine 2+ fuori indice | canonical autoreferenziale | 0,5 | verificato |
| SEO-10 | P2 | Contenuti | `/sponsor` | `danesi.it` e `impiantipolisnc.it` senza DNS; `gogofirenze.it` con certificato non valido | Link morti verso gli sponsor | Redazione: aggiornare gli URL | 0,25 | verificato |
| SEO-11 | P2 | SEO | `app/Http/Middleware/ServeSocialCrawlerMeta.php:264-271` | `og:description` vuota sui prodotti | Anteprime WhatsApp/Facebook senza testo | Ripiego sul nome o sulla categoria | 0,25 | verificato |
| SEO-12 | P2 | Contenuti | `/stagione/cev`, `/stagione/coppa-italia` | `games=[]`, nessun sync CEV (`RisultatiController.php:33-36`) | Voci di menu vuote finché non inizia la competizione | Stato vuoto esplicito o voce nascosta; sync/classifica CEV entro dicembre | 0,5 (+3–12) | verificato |
| SEO-13 | P2 | SEO | pagina `regolamento-aste` | `meta_description` vuota (unica pagina) | — | Redazione | 0,1 | verificato |
| SEO-14 | P2 | SEO | menu/footer | 19 voci su 63 passano da un 301/302 (tutti voluti, nessuno rotto) | Un salto in più | Aggiornare gli URL alle destinazioni finali | 0,5 | verificato |
| R3 | P2 | Contenuti | `routes/pubbliche/sito.php:119-131` | `/summer-camp` è 404 (pagina in bozza); `/summer-camp/info` e `/iscrizione` fanno 301 verso quel 404 | Vecchi link indicizzati finiscono su 404 | Pubblicare la pagina a stagione, oppure ripuntare i redirect | 0,5 | verificato |
| R7 | P2 | Contenuti | Foto ufficiale | PDF non caricato (voce già nascosta) | — | Redazione | 0,1 | verificato |
| R9 | P2 | Documentazione | `docs/GO_LIVE.md:307` | Dice «Sentry spento», ma la spec ha il DSN | Istruzioni fuorvianti | Aggiornare il paragrafo | 0,1 | verificato |
| PERF-2 | P2 | Performance | `/news`, `/stagione` | Lighthouse: `/news` 4,5 MB con copertine originali PNG da 0,9–1,3 MB (le conversioni `card` esistono); `/stagione` 2,5 MB, risparmio stimato 1,5 MB | Pagine pesanti su mobile | Capire quale componente usa l'originale invece di `card`; `srcset` sulle foto della rosa | 2 | verificato (causa da confermare) |
| PERF-3 | P2 | Performance | dettaglio notizia | Lighthouse mobile `/news/a-courmayeur-…`: CLS **0,158** (soglia 0,1), LCP 4,8 s | Il contenuto salta durante il caricamento | Dimensioni esplicite (width/height o aspect-ratio) sulla copertina | 0,5 | verificato |
| UI-1 | P2 | Grafica | `Pages/Public/Societa/Safeguarding.vue:56` | `/images/pattern.svg` → 404 (decorazione al 10% di opacità) | Errore in console, nessun effetto visibile | Aggiungere il file o togliere la classe | 0,1 | verificato |
| UI-2 | P2 | Grafica | menu mobile | Il pulsante flottante delle preferenze cookie copre la voce «Sociale» nel menu aperto a 375 px (`review-screenshots/fase1/webkit/375/_menu-mobile-sottomenu.jpg`) | Il tap su quel punto apre le preferenze cookie invece della voce | Nascondere il pulsante a menu aperto, o z-index | 0,5 | verificato |
| UI-3 | P2 | Robustezza | `CartDrawer` | Su un 429 `/shop/carrello/data` restituisce HTML → `SyntaxError: Unexpected token '<'` in console | Contatore del carrello vuoto per quel caricamento | Controllare `response.ok` prima del `json()` | 0,25 | verificato |
| LINT | P2 | Qualità | ESLint | 0 errori, 462 warning (257 `attributes-order`, 30 `no-v-html` tutti sanificati con DOMPurify, 15 `no-static-element-interactions`…) | Nessuno immediato | Pulizia graduale | — | verificato |

---

## Cosa è stato controllato ed è in ordine (con prova)

- **Build e test** (Fase 0 su `main`): `npm run build` ok; ESLint 0 errori; Vitest 38 file / 307 test ok; Pint ok; PHPStan 0 errori; PHPUnit **1614 passati, 1 saltato** (1615) su DB privato `sito_savino_qa4`; `composer audit` 0 advisory; `npm audit` 0 vulnerabilità (sia prod sia completo).
- **Sicurezza**: 31 `v-html` tutti passati da `sanitize()` (DOMPurify); nessuna SQL injection nei `*Raw`; FormRequest/validate, honeypot e throttle con prefisso su ogni POST pubblica; niente `$guarded = []`; header live: CSP con nonce, HSTS 1 anno, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`; cookie `secure; httponly; samesite=lax`; `APP_DEBUG=false`; `/.env`, `/.git` 403, `/telescope`, `/horizon`, `/_ignition`, `/storage/logs` 404; policy del pannello, IDOR ordini, firme dei webhook Stripe/PayPal/Resend.
- **Visivo**: 384 schermate (48 pagine × 375/768/1280/1920 × Chromium/WebKit) → **0 overflow orizzontali**, 376×200 + 8×404 attesi (pagina inventata), nessuna immagine rotta, nessuna immagine senza `alt`, un solo `<h1>` per pagina. Menu mobile aperto e sottomenu ok su entrambi i motori.
- **Correttezza**: nessun `dd/dump/ray/var_dump/debugger`, nessun TODO/FIXME; tutte le 451 rotte hanno controller e metodo; tutti i `route('…')` esistono; tutte le pagine Inertia hanno il `.vue`; pagine d'errore 403/404/419/429/500/503 brandizzate; lock e arrotondamenti del checkout.
- **SEO**: sitemap valida (2.010 URL, `xmllint`), canonical e hreflang it/en/x-default, 404 veri, OG per i crawler, JSON-LD `SportsTeam`, feed `/feed` valido. Il `noindex` sull'host di anteprima è voluto e si spegne da solo sul dominio (`config/app.php` `indexable_hosts`).
- **Contenuti**: 152 URL interne senza link rotti; niente lorem ipsum, example.com o TODO nei contenuti; favicon 200; anno del footer dinamico.
- **Legale**: P.IVA, ragione sociale, sede, REA e capitale nel footer; privacy, cookie, condizioni, recesso e dichiarazione di accessibilità 200 in IT ed EN; **nessun tracker prima del consenso** (HTML iniziale senza gtag/fbevents/iframe; `analytics.js:54-68`, `meta-pixel.js:24-45`); «Rifiuta tutti» con lo stesso peso di «Accetta»; informativa in tutti i moduli.
- **Produzione**: http → https 301; README con setup, deploy e variabili; `.env.example` con 91 variabili; `docs/GO_LIVE.md` con la procedura del dominio.

## Cosa NON ho potuto verificare, e perché

- **Pagamento end-to-end** (PayPal, bonifico): richiederebbe ordini veri in produzione. I flussi sono coperti dai test (`PayPalIncassoTest` e altri, verdi).
- **Area cliente e pannello autenticati sul sito vero**: niente login in produzione, per regola. Il pannello è coperto da `PanelSmokeTest`.
- **Invio reale delle email**: nessuna email mandata. Il destinatario di C3 è verificato con il mailer `array`.
- **Lighthouse dopo le correzioni**: il branch non è in produzione e in locale i dati (tutte le slide sono la stessa immagine) non riproducono il peso reale. PERF-1 è verificato sul DOM; il numero finale va rimisurato dopo il deploy.
- **Title finale impostato da `<Head>` per ogni pagina**: verificato sul codice e a campione nel browser, non su tutte le 2.010 URL.
- **Stato della chiave Spaces `DO002PZM…`** (S2): `doctl spaces keys list` mi è stato negato.
- **Screen reader reale** (VoiceOver/NVDA): ha un protocollo manuale in `docs/ACCESSIBILITA.md`.
- **Contrasto e tabelle con i dati veri**: in locale Risultati ha una sola gara e nessuna classifica; la correzione è verificata col calcolo (7,1:1) e con axe su cookie-policy.

## Screenshot

`review-screenshots/` (nel worktree, **non committata**: sono circa 130 MB per giro e il repo è pubblico):
- `fase1/<motore>/<larghezza>/<pagina>.jpg`: primo giro. Le sezioni con animazione «reveal» vi risultano vuote perché non erano ancora entrate in vista: è un effetto della cattura, verificato nel browser reale.
- `fase1-scroll/…`: secondo giro, con la pagina fatta scorrere prima dello scatto (è quello da guardare). `_home-prima-visita-banner.jpg` mostra il banner cookie, `_menu-mobile-aperto.jpg` e `_menu-mobile-sottomenu.jpg` il menu.
- `report.json` di ciascun giro: stato HTTP, overflow, errori di console e violazioni axe per ogni scatto.
- `lighthouse-fase1/`: i report HTML e JSON.

---

## Fase 3 — verifica finale (27/09, sul branch dopo le correzioni)

| Controllo | Fase 1 (`main`) | Fase 3 (`review/pre-consegna`) |
|---|---|---|
| `npm run build` | ok | ok |
| ESLint | 0 errori, 462 warning | 0 errori, 462 warning (nessun warning nuovo nei file toccati) |
| Vitest | 307/307 | 307/307 |
| `php -l` su `app/` | ok | ok |
| Pint | ok | ok |
| PHPStan | 0 errori | 0 errori |
| PHPUnit | 1614 passati + 1 saltato | **1618 passati + 1 saltato**. I 4 test nuovi: `RobotsTxtTest`, `PromemoriaDelBonificoTest`, il destinatario in `ContactFormTest`, `LimiteDelleLettureTest`. Ognuno è stato provato anche **senza** la correzione, e in quel caso fallisce |
| axe locale (Risultati, Classifica, Cookie policy, 375 px) | 2 regole serious | 0 violazioni |
| Hero home (DOM, locale) | tutte le slide con lo sfondo subito | all'avvio 1 immagine + 1 sfondo, poi una slide alla volta, 0 errori JS |
| Redirect slash finale (Apache locale) | `Location: http://…` | `Location: https://…` |

**Screenshot del secondo giro sul sito live** (`fase1-scroll/`): 384 scatti, 376 risposte 200 e 8 404 attesi, **0 overflow**, 0 risposte 429, menu mobile aperto su entrambi i motori. Le sole violazioni axe (A11Y-1/2) sono quelle già corrette sul branch e non ancora in produzione.

**Lighthouse mobile, produzione (codice di `main`)**:

| Pagina | Perf | A11y | BP | SEO* | LCP | CLS | Peso |
|---|---|---|---|---|---|---|---|
| `/` | 74 | 100 | 96 | 69 | 20,4 s | 0 | 4.095 KB |
| `/news` | 84 | 99 | 100 | 69 | 4,4 s | 0 | 4.538 KB |
| `/stagione` | 80 | 99 | 96 | 69 | 4,8 s | 0 | 2.544 KB |
| `/shop` | 95 | 99 | 100 | 69 | 2,8 s | 0 | 529 KB |
| `/stagione/risultati` | 79 | 95 | 100 | 69 | 4,2 s | 0 | 719 KB |
| `/contatti` | 91 | 99 | 100 | 69 | 3,4 s | 0 | 488 KB |
| notizia | 75 | 100 | 100 | 69 | 4,8 s | 0,158 | 692 KB |
| `/ticketing/abbonamenti` | 95 | 100 | 100 | 69 | 2,7 s | 0 | 1.656 KB |

\* SEO a 69 **per scelta**: sull'host di anteprima il sito risponde `X-Robots-Tag: noindex`, che sparisce da sé sul dominio definitivo (`indexable_hosts`). Il confronto dopo le correzioni (home con PERF-1, Risultati con A11Y) si può fare solo dopo il deploy: è nella checklist.

**Regressioni:** nessuna. I test, i controlli statici e lo stesso insieme di pagine danno lo stesso esito o migliore.

## P2 rimandati a dopo la consegna

C2, C4, C5, C6, C7, C9/S4, S5, S6, S7, S8, SEO-6, SEO-7, SEO-8, SEO-10, SEO-11, SEO-12, SEO-13, SEO-14, R3, R7, R9, PERF-2, PERF-3, UI-1, UI-2, UI-3, LINT. Stima complessiva: circa 20 h, di cui 3 per la migrazione del plugin translatable.

---

## Checklist di consegna (da fare a mano prima di giovedì)

**Sicurezza — Marco**
- [ ] **Ruotare `APP_KEY`** (S1): `php artisan key:generate --show` in locale, poi cifrarla nella spec su **web, worker e scheduler**. Mettere la vecchia in `APP_PREVIOUS_KEYS`, rilanciare `social:sync-meta` o ricifrare i token Meta, poi togliere `APP_PREVIOUS_KEYS`. Verificare `php -r 'echo strlen(getenv("APP_KEY"));'` dalla console di ciascun componente.
- [ ] Ruotare anche la chiave ActiveCampaign (S6), già che ci sei.
- [ ] `doctl spaces keys list --context savino`: se c'è `DO002PZM…`, revocarla (S2).
- [ ] Decidere se rendere **privato** il repo GitHub: è l'unico modo di togliere dalla history la proposta riservata e le chiavi vecchie.

**Rilascio — Marco**
- [ ] Rivedere e mergiare `review/pre-consegna` (11 commit), poi controllare che la CI e il deploy siano verdi.
- [ ] Dopo il deploy: `curl -s https://seashell-app-47mmf.ondigitalocean.app/robots.txt | grep build` non deve restituire niente; `curl -sI …/contatti/` deve dare `location: https://…`.
- [ ] Rilanciare Lighthouse mobile sulla home: l'LCP deve scendere nettamente sotto i 20 s.
- [ ] Mandare un messaggio di prova dal modulo Contatti e verificare che arrivi a `info@`.

**Cliente e perimetro**
- [ ] Mandare l'elenco delle differenze dalla Proposta (R1) e fissare cosa va in una fase 2.

**Redazione**
- [ ] Rose e staff di B1, U17 e U15, oppure nascondere le tre voci (R2).
- [ ] PDF della guida taglie (SEO-3) e dei magazine (R6), oppure nascondere le voci.
- [ ] Prodotti: descrizioni, nomi home/away distinti, refuso «GARAVAN», «Stato dell'articolo» per le maglie indossate o firmate (SEO-4).
- [ ] Beneficiario del bonifico esatto, da chiedere all'amministrazione (SEO-5).

**Giorno del passaggio (docs/GO_LIVE.md)**
- [ ] Ultimo `news:importa-dal-vecchio-sito`, poi `domains:` + `TRUSTED_HOSTS` nello stesso commit, modifica (non ricreazione) del webhook PayPal e `paypal:verifica`, `gh variable set URL_SITO` (R12).
- [ ] `php artisan verifica:lancio` dalla console di **ognuno** dei tre componenti: 0 blocchi.
- [ ] Search Console: «Controllo URL» su home e su una notizia, controllando che la pagina renderizzata abbia il testo (verifica di SEO-1), e invio della sitemap.
