# Passaggio in produzione — 1 ottobre 2026

Il giorno in cui `savinodelbenevolley.it` smette di servire il vecchio sito
WordPress e comincia a servire questa applicazione.

Non è un deploy: il codice è già in produzione e il sito gira da mesi su
`https://seashell-app-47mmf.ondigitalocean.app`. Quello che cambia è il
dominio, e con lui alcune cose che oggi non si notano perché quell'indirizzo
non lo guarda nessuno.

Il controllo di partenza, dalla console dell'app:

```
php artisan verifica:lancio
```

Elenca quello che manca e distingue i blocchi dalle cose da guardare. Quello
che non può sapere lo dice: il webhook di PayPal va chiesto a PayPal con
`php artisan paypal:verifica`.

> Va lanciato sulla console di **tutti e tre i componenti** — `web`, `worker`,
> `scheduler` — non solo del primo: sono tre ambienti distinti e una variabile
> scritta in uno non arriva agli altri. È così che `APP_KEY` è rimasta vuota
> per mesi su due di essi (§2).

---

## Prima del giorno X

### 1. La posta — risolto il 25/09/2026

> **Stato:** dal 25/09/2026 (commit `15a6360`) `MAIL_MAILER=resend` è nella
> spec a livello d'app e il dominio è verificato su Resend (DKIM e record
> d'invio sul DNS della Spa). Quello che segue è la storia di come ci si è
> arrivati e i record da non far togliere: resta utile se la Spa ritocca il
> DNS.

`MAIL_MAILER` non è nella spec, quindi `config/mail.php` cade sul valore di
serie `log`: **le email vengono scritte nel log e non spedite**. Finché il sito
vive su un indirizzo che nessuno usa non se ne accorge nessuno; dal 1 ottobre
significa che un cliente paga e non riceve niente.

Cosa non parte oggi:

| Quando | Email |
| --- | --- |
| Ordine pagato | `OrderConfirmation` |
| Ordine spedito | `OrderShipped` |
| Ordine annullato / rimborsato | `OrderCancelled`, `RefundConfirmation` |
| Stato dell'ordine cambiato | `OrderStatusChanged` |
| Asta vinta / offerta superata | `AuctionWon`, `AuctionOutbid` |
| Password in scadenza, reimpostazione | `PasswordExpiringSoon`, reset di Laravel |

Resend è già installato (`resend/resend-laravel`) e il mailer `resend` è già in
`config/mail.php`: manca la configurazione. Servono, **su `web` e `worker`**
(le email partono dalla coda, quindi il worker deve avere le stesse variabili):

```yaml
- key: MAIL_MAILER
  value: resend
- key: MAIL_FROM_ADDRESS
  value: noreply@savinodelbenevolley.it
- key: MAIL_FROM_NAME
  value: Savino Del Bene Volley
- key: RESEND_API_KEY
  type: SECRET
  value: EV[1:…]
```

A carico di chi gestisce il dominio: account Resend, verifica DNS (SPF e DKIM)
e generazione della chiave. La chiave si inserisce dal pannello DO con
"Encrypt", poi si recupera il blob cifrato con `doctl apps spec get` e si
committa — vedi `docs/INFRASTRUCTURE.md` sulla spec autorevole.

#### Il DNS non e' nostro, e oggi rifiuta Resend

Questa e' la parte con il tempo di attesa piu' lungo, e **non dipende dal
passaggio del dominio**: si puo' — si deve — avviare subito. Com'e' il DNS di
`savinodelbenevolley.it` oggi (verificato il 23/09/2026):

| | |
| --- | --- |
| Nameserver | `dns2/dns4/dnsusa.sdb.it` — il DNS e' della Spa, non nostro |
| MX | Proofpoint (`*.pphosted.com`), davanti a Microsoft 365 |
| SPF | `v=spf1 include:spf.protection.outlook.com include:spf-0067d401.pphosted.com include:servers.mcsv.net -all` |
| DMARC | `v=DMARC1; p=reject; …` con rapporti a Proofpoint |
| DKIM Resend | assente |

`p=reject` con `-all` non significa "finisce in spam": significa che una email
spedita via Resend da `@savinodelbenevolley.it` viene **rifiutata** dal
destinatario. Impostare `MAIL_MAILER=resend` senza prima passare dal DNS
sostituisce quindi un guasto silenzioso (la posta nel log) con uno rumoroso, e
non manda niente lo stesso.

L'ordine e' obbligato, perche' i valori da chiedere non esistono prima del
primo passo:

1. **Creare il dominio su Resend** (o meglio il sottodominio d'invio, sotto).
   Resend genera allora i record esatti da inserire: e' l'unico modo di avere i
   valori veri, e lo si puo' fare oggi.

   > **Il dominio va prima reclamato, e non e' il passo che ci si aspetta.**
   > Il pannello e' quello della Spa (team `savinodelbene`, account di Sandra
   > Leoncini, che l'ha affidato a noi per la configurazione). Li'
   > `savinodelbenevolley.it` e' stato aggiunto il 23/09/2026 ma resta fermo su
   > **"Claim domain"**, con l'avviso che il dominio *e' gia' in uso da un altro
   > team Resend* e che verificarne la proprieta' lo trasferisce a questo team
   > **revocando l'accesso all'altro**. Quale sia l'altro team dal pannello non
   > si vede. Marco ha confermato il 25/09/2026 che non lo usa nessuno e che si
   > procede col trasferimento.
   >
   > La conseguenza pratica e' sull'ordine dei record: **il primo TXT da
   > mettere in zona non e' il DKIM, e' la verifica di proprieta'** — tipo TXT,
   > nome `@` (l'apex, non un sottodominio), contenuto
   > `resend-domain-verification=…` come lo mostra il pannello. I record
   > d'invio (DKIM, e l'SPF/MX del sottodominio) compaiono **solo dopo** che il
   > claim e' andato a buon fine: chiederli tutti insieme alla Spa significa
   > chiedere valori che ancora non esistono.
   >
   > Il claim non e' un pulsante: e' il pulsante *dopo* che il TXT e' in zona.
   > Premuto senza, Resend segna "Checking DNS" negli eventi del dominio e lo
   > stato resta `Not Started`, senza dire altro.
   >
   > **`resend-domain-verification=<token>` e' tutto contenuto, non
   > nome=valore.** Va su `@` — cioe' il TXT dell'apex, accanto a `MS=…` e
   > all'SPF — con la stringa intera, segno di uguale compreso. Il 25/09/2026
   > e' stato inserito invece come sottodominio,
   > `_resend-domain-verification.savinodelbenevolley.it` con valore il solo
   > token: leggibile con `dig` e apparentemente a posto, ma Resend non lo
   > trova e lo stato resta `Not Started` senza spiegare perche'. L'equivoco e'
   > comprensibile, perche' molti altri servizi usano davvero un
   > `_qualcosa.dominio`: e' il tipo di errore che costa un giro di richieste
   > se non lo si nomina in anticipo. Si controlla con
   > `dig +short TXT savinodelbenevolley.it`, che deve stampare **tre** righe.

   > **Esito (25/09/2026): il claim e' riuscito.** Corretto il record, il
   > dominio e' passato al team `savinodelbene`, regione **Ireland
   > (eu-west-1)**, stato `Pending`. Solo allora Resend ha mostrato i record
   > d'invio, che sono due:
   >
   > | Tipo | Nome | Serve a |
   > | --- | --- | --- |
   > | TXT | `resend._domainkey` | la firma DKIM (una chiave RSA di 218 caratteri) |
   > | CNAME | `rsend` | il percorso d'uscita |
   > | CNAME | `send` | l'altra meta' dello stesso percorso |
   >
   > **Sono tre, e vanno chiesti insieme.** Nel pannello i due CNAME stanno
   > sotto l'intestazione `SPF`, dentro *Enable Sending*; *Enable Receiving* e'
   > la sezione **successiva**, e a colpo d'occhio sembra invece che il secondo
   > CNAME appartenga a quella. Chiesti solo i primi due (e' successo il
   > 25/09/2026) il dominio arriva a `Partially Verified` — DKIM e `rsend`
   > verificati — ma l'invio resta spento, con l'avviso "Missing SPF records:
   > add them to enable sending", e chi tiene la zona va disturbato una seconda
   > volta.
   >
   > I valori non stanno qui, si leggono dal pannello. Quello del DKIM **non si
   > copia da uno screenshot ne' dal testo della pagina**: il pannello lo taglia
   > con un `[…]` in mezzo e il nome del pulsante di copia si ferma a cento
   > caratteri. Va preso dall'`aria-label` intero, o con il pulsante Copia. Un
   > carattere sbagliato non da' errore: da' firme che non si verificano.
2. **Chiedere alla Spa di aggiungerli.** Conviene chiedere un **sottodominio
   d'invio** — `send.savinodelbenevolley.it` — invece dell'apex: i record
   nascono sotto un nome che non esiste ancora, l'SPF dell'apex (che e' la posta
   aziendale, Microsoft 365 dietro Proofpoint) **non si tocca**, e la richiesta
   diventa innocua da approvare. Il mittente resta
   `noreply@savinodelbenevolley.it`: DMARC si accontenta dell'allineamento del
   DKIM, che e' sullo stesso dominio organizzativo.
3. **Verificare il dominio su Resend** quando i record sono propagati, generare
   la chiave e solo allora mettere le variabili nella spec.

Alla stessa richiesta conviene allegare l'altra cosa che serve dalla Spa, e che
va chiesta con qualche giorno d'anticipo: **abbassare il TTL** dei record `A` di
`savinodelbenevolley.it` e `www` a 300 secondi. Oggi e' di due ore: lasciato
com'e', il giorno del passaggio una parte dei visitatori continua a vedere il
vecchio sito per ore dopo lo spostamento, e non c'e' modo di accorciare
l'attesa a posteriori.

> **Non committare `MAIL_MAILER=resend` senza la chiave.** L'invio fallirebbe
> con un errore invece di ricadere sul log, che almeno non perde il messaggio.

Prova dopo l'attivazione, dalla console:

```
php artisan tinker --execute="Mail::raw('prova', fn(\$m) => \$m->to('marco@mv-consulting.it')->subject('Prova invio'));"
```

### 2. Le variabili dei tre componenti — chiuso, ma è la famiglia di guasti da sorvegliare

> **Stato al 23/09/2026: rientrato.** Dalla console dei tre componenti
> `APP_KEY` è ora presente e **identica** (51 caratteri, stessa impronta su
> `web`, `worker`, `scheduler`). Resta scritto perché è il difetto che questo
> impianto produce più facilmente, e perché la correzione si può disfare con un
> deploy distratto.

Com'era: `APP_KEY` era di 51 caratteri sul servizio `web` e **vuota** su
`worker` e `scheduler` (il valore cifrato nello spec si decifrava in stringa
vuota, ed era lo stesso blob su entrambi).

Non si vede, e infatti non l'ha visto nessuno: `schedule:work` manda l'output
dei comandi in `/dev/null` e Sentry è spento. Ma `social:sync-meta` fallisce
ogni notte da sempre — `social_accounts.access_token` ha il cast `encrypted` —
e le poche righe in `social_insights_daily` sono state scritte aprendo la
pagina del pannello, mai alle 03:30. Dal giorno in cui la posta parte davvero
si aggiunge il danno peggiore: il link di disiscrizione dalla newsletter nasce
nella mail in coda, cioè sul worker, e una firma fatta con una chiave diversa
da quella del web **non si verifica** — ogni disiscrizione risponde 403.

La correzione è nello spec (`.do/app.yaml`): lo stesso blob cifrato del
servizio `web` su tutti e tre i componenti. I valori cifrati sono legati
all'app, non al componente, quindi si copiano.

Dopo il deploy, dalla console di **ciascun** componente:

```
doctl apps console <app-id> web        # poi worker, poi scheduler
php -r 'echo strlen(getenv("APP_KEY")), "\n";'
```

Deve dire 51 su tutti e tre. Lo stesso controllo lo fa `verifica:lancio`, che
però va lanciato su ogni componente: le variabili non sono condivise.

**Lanciarlo su tutti e tre non è una formalità.** Il 23/09/2026, fatto davvero,
ha trovato un secondo caso: le quattro variabili `PAYPAL_*` stavano solo sotto
`services:`, quindi `verifica:lancio` dava *due* blocchi su `worker` e
`scheduler` (posta e pagamenti) contro l'unico del `web`. Là non rompeva ancora
niente — nessun job in coda costruisce `PayPalPaymentService`, il checkout, il
webhook e il pannello sono tutte richieste web — ma il primo rimborso messo in
coda sarebbe fallito senza lasciare traccia. Corretto nello spec insieme a
`APP_LOCALE`, che per lo stesso motivo mancava (le email in coda sono rese dal
worker, e la lingua combaciava solo grazie al ripiego di `config/app.php`).

Perché non capiti una terza volta, il confronto fra le tre liste è un test:
`tests/Unit/VariabiliAllineateFraIComponentiTest.php`. Ogni variabile del `web`
deve esistere anche su worker e scheduler, salvo quelle che vivono dentro una
richiesta HTTP (`SESSION_*`, `PREVIEW_AUTH_*`, `INERTIA_SSR_ENABLED`), elencate
lì una per una con il motivo. Verifica anche che `APP_KEY` sia lo stesso blob:
averla su tutti e tre ma **diversa** è il caso peggiore, perché non somiglia a
un guasto — le firme prodotte dalla coda semplicemente non si verificano dal
web.

### 3. Il webhook di PayPal — si modifica, non si ricrea

`PAYPAL_WEBHOOK_ID` (`13C19054TT859171H`) identifica il webhook registrato su
developer.paypal.com, ed è puntato su
`https://seashell-app-47mmf.ondigitalocean.app/api/webhooks/paypal`. Quell'id
entra nella verifica della firma di ogni notifica, e il capture del pagamento
avviene dentro il webhook: con l'indirizzo sbagliato **gli ordini restano
"pending" e nessuno incassa**, senza che compaia un errore da nessuna parte.

**Modificare l'URL del webhook esistente** (`PATCH` dell'API, o il pannello di
PayPal), non crearne uno nuovo:

- l'id resta valido, quindi `.do/app.yaml` non va toccato e non serve un
  deploy sincronizzato con il DNS;
- non esiste la finestra in cui la firma si verifica contro un webhook e le
  notifiche arrivano a un altro.

Nuovo indirizzo: `https://savinodelbenevolley.it/api/webhooks/paypal`.

Subito dopo, dalla console:

```
php artisan paypal:verifica
```

Deve dire che le credenziali sono buone, che il webhook esiste, che punta a
questo sito e che ascolta `CHECKOUT.ORDER.APPROVED` e
`PAYMENT.CAPTURE.REFUNDED`.

### 4. Le notizie del vecchio sito

Fino al passaggio la redazione pubblica ancora là. Lo scheduler le riprende
ogni ora e si spegne da solo il 2 ottobre, ma **l'ultimo giro va fatto a mano
subito prima di spostare il DNS**: staccato il vecchio sito, quei comunicati
non sono più recuperabili.

```
php artisan news:importa-dal-vecchio-sito --prova
php artisan news:importa-dal-vecchio-sito
```

Se il passaggio slitta, spostare `VECCHIO_SITO_FINO_A` invece di toccare il
codice.

### 5. La scansione dei cookie guarda ancora l'indirizzo di anteprima

Il workflow `scansione-cookie.yml` visita `vars.URL_SITO`, e quella variabile
di repository **non esiste**: ripiega sull'indirizzo `ondigitalocean.app`. I
domini che finiscono nella dichiarazione pubblica dei cookie sono quelli del
sito scansionato — oggi `.seashell-app-47mmf.ondigitalocean.app`, che dopo il
passaggio si leggerebbe in fondo alla Cookie Policy del sito vero.

Si crea la variabile (non è un segreto):

```
gh variable set URL_SITO --body "https://savinodelbenevolley.it"
```

e si rilancia la scansione a mano (`workflow_dispatch`) invece di aspettare il
lunedì.

### 6. Da decidere, non bloccanti

- **Stripe**: senza chiavi la carta di credito non viene offerta. È gestito,
  non rotto (`PaymentGateway::configurato()`), ma al lancio resta il solo
  PayPal più il bonifico.
- **Sentry**: `SENTRY_LARAVEL_DSN` è vuoto, quindi nessun errore di produzione
  viene raccontato — e `LOG_LEVEL=error` nasconde gli avvisi. Il giorno del
  lancio è quello in cui serve di più.

---

## Il giorno X, nell'ordine

L'ordine conta. Il dominio va aggiunto **all'app prima** che il DNS lo mandi
lì: al contrario, per il tempo che passa fra le due cose App Platform non
riconosce l'host e risponde 404 a tutti.

1. **Ultimo import delle notizie** (§4).

2. **Aggiungere il dominio alla spec.** Oggi `.do/app.yaml` **non ha nessuna
   sezione `domains:`**, e la spec è autorevole: un dominio aggiunto solo dal
   pannello DigitalOcean **viene cancellato al primo deploy successivo**. Va
   messo nel file:

   ```yaml
   domains:
   - domain: savinodelbenevolley.it
     type: PRIMARY
   - domain: www.savinodelbenevolley.it
     type: ALIAS
   ```

   > **Perché non è già scritto qui.** `APP_URL` vale `https://${APP_DOMAIN}`,
   > cioè il dominio PRIMARY. Aggiungendo la sezione prima del passaggio, ogni
   > indirizzo generato fuori da una richiesta — link nelle email in coda,
   > sitemap, feed RSS, indirizzi di ritorno dei pagamenti — punterebbe a
   > `savinodelbenevolley.it` mentre là risponde ancora WordPress. Si aggiunge
   > quando si sposta il DNS, non prima.

3. **Spostare il DNS** sull'app (record A/CNAME secondo il pannello DO).

4. **Attendere il certificato.** DO lo emette da solo quando il DNS risolve.
   Finché è in corso, `https://` può dare errore di certificato: è normale,
   non è il sito rotto.

5. **Rilanciare i controlli** dalla console:

   ```
   php artisan verifica:lancio
   php artisan paypal:verifica
   php artisan file:verifica
   php artisan menu:verifica
   ```

6. **Provare a mano** la cosa che porta soldi: un ordine vero di prova sullo
   shop, fino alla mail di conferma. È l'unico controllo che attraversa tutta
   la catena — checkout, webhook, coda, posta.

---

## Dopo

- Il feed RSS cambia indirizzo solo nel dominio: resta `/feed`, che è lo stesso
  che serviva WordPress. Chi era già abbonato non deve rifare niente — è il
  motivo per cui è stato tenuto lì (vedi `CLAUDE.md` §22).
- `news:importa-dal-vecchio-sito` non serve più: lo scheduler si spegne da
  solo, il comando si può togliere.
- Il vecchio sito, una volta staccato, non è più interrogabile: tutto quello
  che serviva recuperare da lì va recuperato prima.

## Cose che si sistemano da sole (non toccare)

- **L'indicizzazione si riapre da sé.** Finché si risponde sull'indirizzo di
  anteprima ogni pagina esce con `X-Robots-Tag: noindex, nofollow`, perché il
  dominio ufficiale serve ancora WordPress e i due sarebbero contenuto
  duplicato. Non è un interruttore da ricordarsi di spegnere: la regola è
  `config('app.indexable_hosts')`, che elenca i domini definitivi, e appena il
  sito risponde lì l'header smette di comparire.
- **`canonical` e la sitemap seguono `APP_URL`**, che vale `https://${APP_DOMAIN}`:
  cambiano con il dominio, senza interventi. L'unico indirizzo scritto a mano è
  la direttiva `Sitemap:` di `public/robots.txt`, ed è sull'apex senza `www`,
  come il canonico.
