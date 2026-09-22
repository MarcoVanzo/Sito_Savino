# Passaggio in produzione — 1 ottobre 2026

Il giorno in cui `savinodelbenevolley.it` smette di servire il vecchio sito
WordPress e comincia a servire questa applicazione.

Non è un deploy: il codice è già in produzione e il sito gira da mesi su
`https://seashell-app-47mmf.ondigitalocean.app`. Quello che cambia è il
dominio, e con lui alcune cose che oggi non si notano perché quell'indirizzo
non lo guarda nessuno.

Il controllo di partenza è uno solo, dalla console dell'app:

```
php artisan verifica:lancio
```

Elenca quello che manca e distingue i blocchi dalle cose da guardare. Quello
che non può sapere lo dice: il webhook di PayPal va chiesto a PayPal con
`php artisan paypal:verifica`.

---

## Prima del giorno X

### 1. La posta — è il blocco vero

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

> **Non committare `MAIL_MAILER=resend` senza la chiave.** L'invio fallirebbe
> con un errore invece di ricadere sul log, che almeno non perde il messaggio.

Prova dopo l'attivazione, dalla console:

```
php artisan tinker --execute="Mail::raw('prova', fn(\$m) => \$m->to('marco@mv-consulting.it')->subject('Prova invio'));"
```

### 2. Il webhook di PayPal — si modifica, non si ricrea

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

### 3. Le notizie del vecchio sito

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

### 4. Da decidere, non bloccanti

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

1. **Ultimo import delle notizie** (§3).

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
