# Accessibilità — controlli automatici e prova con screen reader

Lo shop è soggetto all'European Accessibility Act (EN 301 549 = WCAG 2.1 AA,
CLAUDE.md §11). Qui: cosa controlla la macchina, cosa deve controllare una
persona, e come.

## 1. Cosa è automatico

| Controllo | Dove | Cosa copre |
|---|---|---|
| axe sul sito vero, in sola lettura | `scansione-accessibilita.yml`, lavoro `scansione` (lunedì) | pagine dalla sitemap, scheda prodotto con l'errore "scegli la taglia", carrello vuoto, i due passaggi del recesso (fino alla conferma, che non si preme) |
| axe sui passaggi che scrivono | stesso workflow, lavoro `flussi` | app avviata nel runner con `ScansioneAccessibilitaSeeder`: carrello pieno, checkout passo 1 e 2 con gli errori a schermo, conferma d'ordine a bonifico, checkout di un'asta vinta. `--flussi` rifiuta ogni host non locale |
| axe sui componenti | `npm test` (`Checkout.test.js`, `Auctions/Checkout.test.js`, `PulsanteOrdine.test.js`) | stati del modulo che richiedono un ordine: errori, pulsante, casella delle condizioni. Senza contrasto (jsdom non calcola gli stili) |
| screen reader simulato | `npm test` (`percorsiScreenReader.test.js`, `@guidepup/virtual-screen-reader`) | nomi, ruoli, stati e ordine di lettura di menu, scheda prodotto, carrello, checkout con errori, recesso |

In locale: `node scripts/scansione-accessibilita.mjs --url=http://127.0.0.1:8000 --flussi --solo-flussi`
dopo `php artisan db:seed --class=ScansioneAccessibilitaSeeder` (Node 20/22, Playwright 1.56).

Il simulatore legge l'albero di accessibilità del DOM: non sente come un
utente vero la verbosità, i tempi degli annunci, la navigazione per rotore o i
comportamenti propri di un browser. Per questo serve anche la prova sotto.

## 2. Protocollo di prova manuale (VoiceOver o NVDA)

Da fare a ogni rilascio che tocca shop o layout, e comunque una volta a
trimestre. Durata: circa 45 minuti. Ambiente: sito di staging o produzione con
un **prodotto di prova** e pagamento a **bonifico** (l'ordine si annulla dal
pannello dopo la prova). Non usare carte reali.

**Avvio**
- macOS: Safari + VoiceOver (`Cmd+F5`). Rotore: `Ctrl+Opt+U`. Lettura
  continua: `Ctrl+Opt+A`.
- Windows: Firefox o Chrome + NVDA (gratuito). Elenco elementi: `NVDA+F7`.
  Modalità modulo: entra da sola nei campi.

Per ogni passo annotare: **OK**, oppure cosa si è sentito e cosa ci si
aspettava.

1. **Testata e menu**
   - Il primo Tab porta a «Vai al contenuto», che funziona.
   - Nel rotore/elenco i punti di riferimento sono: banner, «Navigazione
     principale», main, contentinfo.
   - Una voce con sottomenu dice «compresso/espanso»; con Invio si apre, con
     Esc si chiude e il focus torna sulla voce. Le voci del sottomenu si
     leggono con il nome e la descrizione, senza il motto o la foto.
   - Sul telefono (VoiceOver iOS): il pulsante menu dice «Menu, compresso»,
     aperto il pannello il focus resta dentro fino alla chiusura.
2. **Scheda prodotto**
   - Titolo H1 = nome del prodotto; il percorso («Percorso») non legge le
     barre.
   - Il gruppo «Seleziona variante» si annuncia; ogni taglia dice
     «selezionato/non selezionato», quelle esaurite «non disponibile».
   - «Aggiungi al carrello» senza taglia: si sente subito l'avviso, il focus
     va alla prima taglia, l'avviso resta finché non si sceglie.
   - «+» e «−» della quantità: il nuovo numero viene letto.
3. **Carrello**
   - Ogni pulsante dice il prodotto («Aumenta quantità: Maglia …»,
     «Rimuovi dal carrello: …»); dopo un cambio la quantità nuova viene letta.
   - Il messaggio a comparsa (es. «Prodotto aggiunto») viene letto e non
     sparisce finché il focus ci sta sopra.
4. **Checkout, passo 1**
   - Premere «Avanti» a modulo vuoto: si sente l'avviso generale, il focus va
     al primo campo, che si legge come «non valido» con il messaggio
     «Campo obbligatorio».
   - Il campo indirizzo con suggerimenti: le frecce scorrono le proposte e
     ciascuna viene letta; Invio la sceglie.
   - Compilato il passo: «Avanti» porta il focus al titolo «Passo 2 di 2:
     Pagamento», che viene letto.
5. **Checkout, passo 2**
   - Il pulsante d'ordine si legge come «Conferma ordine, Ordine con obbligo
     di pagamento» e **non** come «non disponibile».
   - Premerlo senza metodo e senza condizioni: il focus va al primo metodo di
     pagamento, che dice «non valido, Scegli un metodo di pagamento»; la
     casella delle condizioni dice il suo errore.
   - I tre link della casella (condizioni, recesso, privacy) hanno nomi
     comprensibili fuori contesto.
   - Scelto il bonifico e spuntata la casella, l'ordine parte; la pagina di
     conferma ha un H1 e il numero d'ordine si raggiunge.
6. **Recesso online** (`/recesso`)
   - «Continua» a modulo vuoto: focus sul primo campo, errore letto.
   - Compilato: il focus va al titolo del riepilogo; «Modifica» torna ai dati.
   - Non premere «Conferma recesso» sul sito vero (manda un'email).
7. **Zoom e riflusso** (senza screen reader): browser a 400% su finestra
   larga 1280 px (= 320 px CSS); checkout e carrello si usano senza
   scorrimento orizzontale.

**Esito**: annotare data, sistema, browser, screen reader con versione e la
lista dei difetti. I difetti vanno risolti o aggiunti ai limiti noti della
dichiarazione (`database/data/dichiarazione_accessibilita.php` + migrazione a
guardie). Quando la prova è stata fatta per intero senza difetti aperti, dalla
dichiarazione si toglie il limite «la prova con VoiceOver e NVDA da parte di
una persona non è ancora conclusa».

## 3. Contenuti fuori dal codice

- **Notizie dell'archivio**: i titoli interni (320 notizie) li riallinea al
  deploy la migrazione `2026_09_26_170100_titoli_delle_notizie_in_ordine`,
  che chiama `news:correggi-accessibilita`; le 25 immagini informative
  (21 notizie) hanno l'alt scritto guardandole, nella migrazione a guardie
  `2026_09_26_170000_testi_alternativi_delle_immagini_delle_notizie`. Per i
  comunicati nuovi, `php artisan news:correggi-accessibilita --prova` dice
  quali titoli sistemare ed elenca le immagini ancora senza testo
  alternativo, che va scritto dal pannello guardando l'immagine (vuoto solo
  se è davvero decorativa).
- **PDF caricati dalla società**: verifica con `pdfinfo` (Title, Tagged) e
  `pikepdf` (Lang, StructTreeRoot). Al 26/09/2026 nessuno è pienamente
  conforme: vedi la dichiarazione. Chi prepara un PDF nuovo lo esporta da
  Word/InDesign con «PDF con tag», titolo del documento e lingua impostati.
- **PDF che generiamo noi** (condizioni allegate all'ordine, ricevuta):
  titolo mostrato e lingua (da `<html lang>`) li mette
  `App\Support\Accessibilita\PdfAccessibile`, che sostituisce il wrapper di
  laravel-dompdf (`PdfAccessibileServiceProvider`). dompdf non produce PDF
  taggati: l'alternativa accessibile sono le pagine del sito.
