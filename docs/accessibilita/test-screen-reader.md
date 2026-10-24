# Prova con NVDA e VoiceOver: checklist per il tester

Da compilare da una persona, non da un programma. I controlli automatici (axe
ogni lunedì, screen reader simulato in `npm test`) non dicono se il sito si
usa davvero con uno screen reader: questa prova sì.

Preparazione, ambiente e comandi sono in [`docs/ACCESSIBILITA.md`](../ACCESSIBILITA.md) §2:
prodotto di prova, pagamento a **bonifico**, nessuna carta reale, ordine
annullato dal pannello dopo la prova. Fare il giro **due volte**: Windows +
NVDA (Firefox o Chrome) e macOS + VoiceOver (Safari); il menu anche con
VoiceOver su iPhone.

**La dichiarazione di accessibilità non si modifica finché la prova non è
fatta per intero.** Con i risultati: i difetti si correggono o diventano limiti
noti (`database/data/dichiarazione_accessibilita.php` + migrazione a guardie);
solo a prova completa senza difetti aperti si toglie il limite «la prova con
VoiceOver e NVDA da parte di una persona non è ancora conclusa».

Per ogni riga scrivere **OK** oppure che cosa si è sentito. I criteri sono
quelli WCAG 2.1 livello AA (EN 301 549).

| Data | Sistema | Browser | Screen reader e versione | Tester |
| --- | --- | --- | --- | --- |
| | | | | |

---

## 1. Banner dei cookie

Aprire il sito in una finestra privata (nessuna scelta salvata).

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 1.1 | Caricare la home e scorrere i punti di riferimento | Il banner è una regione con titolo «Gestione Cookie», raggiungibile senza mouse | 1.3.1, 2.1.1 | | |
| 1.2 | Tab dentro il banner | Si raggiungono in ordine logico «Personalizza», «Rifiuta tutti», «Accetta tutti», i link a Privacy e Cookie Policy e la «X»; il focus si vede sempre | 2.4.3, 2.4.7 | | |
| 1.3 | Ascoltare la «X» | Si legge «Chiudi e rifiuta i cookie non necessari», non solo «pulsante» | 4.1.2, 2.4.6 | | |
| 1.4 | «Personalizza» | Le tre caselle si leggono con il nome (Necessari, Analitici, Marketing) e lo stato; «Necessari» è annunciata come non modificabile | 1.3.1, 4.1.2 | | |
| 1.5 | «Rifiuta tutti» | Il banner si chiude e il focus non si perde in cima alla pagina senza motivo | 2.4.3 | | |
| 1.6 | Riaprire dall'icona in basso a sinistra o da «Preferenze cookie» nel footer | Si apre una finestra di dialogo: il focus entra, resta dentro, Esc la chiude e il focus torna all'icona | 2.1.2, 2.4.3, 4.1.2 | | |
| 1.7 | Zoom 200% | Il banner non copre i pulsanti in modo da renderli irraggiungibili | 1.4.4, 1.4.10 | | |

## 2. Testata e menu

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 2.1 | Primo Tab della pagina | «Vai al contenuto», che porta davvero al contenuto | 2.4.1 | | |
| 2.2 | Elenco dei punti di riferimento | banner, «Navigazione principale», main, contentinfo | 1.3.1, 2.4.1 | | |
| 2.3 | Voce con sottomenu | Dice «compresso»; Invio la apre («espanso»); Esc la chiude e il focus torna sulla voce | 4.1.2, 2.1.1 | | |
| 2.4 | Voci del sottomenu | Nome e descrizione, senza motto né foto letti come testo | 1.1.1, 2.4.4 | | |
| 2.5 | iPhone, pulsante menu | «Menu, compresso»; aperto il pannello il focus resta dentro fino alla chiusura | 4.1.2, 2.4.3 | | |
| 2.6 | Cambiare pagina dal menu | Il titolo della pagina nuova viene letto e il focus va al contenuto | 2.4.2, 2.4.3, 4.1.3 | | |

## 3. Scheda prodotto con scelta della taglia

Usare un prodotto con taglie, almeno una esaurita.

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 3.1 | Titoli della pagina | H1 = nome del prodotto; il percorso non legge le barre | 1.3.1, 2.4.6 | | |
| 3.2 | Immagini del prodotto | Testo alternativo che descrive il prodotto, non il nome del file | 1.1.1 | | |
| 3.3 | Prezzo scontato | Si capisce qual è il prezzo da pagare e qual è il prezzo precedente | 1.3.1 | | |
| 3.4 | Gruppo delle taglie | Si annuncia «Seleziona variante»; ogni taglia dice selezionata o no, le esaurite «non disponibile» | 1.3.1, 4.1.2 | | |
| 3.5 | «Aggiungi al carrello» senza taglia | Avviso letto subito, focus sulla prima taglia, l'avviso resta finché non si sceglie | 3.3.1, 3.3.3, 4.1.3 | | |
| 3.6 | «+» e «−» della quantità | Il nome dice cosa fanno; il nuovo numero viene letto | 4.1.2, 4.1.3 | | |
| 3.7 | «I tuoi diritti di garanzia legale» | Apre una finestra con titolo; l'immagine ha la trascrizione; «Chiudi» funziona e il focus torna al link | 1.1.1, 2.4.3, 4.1.2 | | |
| 3.8 | Personalizzazione con la firma (se presente) | Si capisce che esclude il recesso prima di sceglierla | 3.3.2 | | |

## 4. Carrello

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 4.1 | Messaggio «Prodotto aggiunto» | Viene letto e non sparisce mentre il focus ci sta sopra | 4.1.3, 2.2.1 | | |
| 4.2 | Pulsanti di ogni riga | Dicono il prodotto: «Aumenta quantità: Maglia …», «Rimuovi dal carrello: …» | 2.4.6, 4.1.2 | | |
| 4.3 | Cambiare quantità | La quantità e il totale nuovi vengono letti | 4.1.3 | | |
| 4.4 | Rimuovere l'ultimo articolo | Si sente che il carrello è vuoto e il focus finisce in un punto sensato | 2.4.3, 4.1.3 | | |

## 5. Checkout con errori

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 5.1 | Campi del passo 1 | Ogni campo ha un'etichetta letta; gli obbligatori lo dicono | 1.3.1, 3.3.2 | | |
| 5.2 | «Avanti» a modulo vuoto | Avviso generale letto; focus sul primo campo, che dice «non valido» e «Campo obbligatorio» | 3.3.1, 3.3.3, 4.1.3 | | |
| 5.3 | Email o CAP scritti male | Il messaggio dice come correggerli, non solo che sono sbagliati | 3.3.3 | | |
| 5.4 | Indirizzo con suggerimenti | Le frecce scorrono le proposte e ciascuna viene letta; Invio la sceglie | 4.1.2, 2.1.1 | | |
| 5.5 | Passo 1 compilato, «Avanti» | Il focus va a «Passo 2 di 2: Pagamento», che viene letto | 2.4.3, 4.1.3 | | |
| 5.6 | Pulsante d'ordine | Si legge «Conferma ordine, Ordine con obbligo di pagamento», mai «non disponibile» | 2.4.6, 3.3.2 | | |
| 5.7 | Premerlo senza metodo e senza condizioni | Focus sul primo metodo, «non valido, Scegli un metodo di pagamento»; la casella delle condizioni dice il suo errore | 3.3.1, 3.3.3 | | |
| 5.8 | Link della casella delle condizioni | Condizioni, recesso e privacy hanno nomi comprensibili fuori contesto | 2.4.4 | | |
| 5.9 | Bonifico, casella spuntata, ordine | Pagina di conferma con H1; il numero d'ordine si raggiunge | 1.3.1, 2.4.6 | | |
| 5.10 | Zoom 400% su finestra di 1280 px | Carrello e checkout senza scorrimento orizzontale | 1.4.10 | | |

## 6. Recesso online (`/recesso`)

**Non premere «Conferma recesso» sul sito vero**: manda un'email.

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 6.1 | Raggiungere `/recesso` dal footer | Il link si trova con l'elenco dei link e si capisce dove porta | 2.4.4 | | |
| 6.2 | «Continua» a modulo vuoto | Focus sul primo campo, errore letto | 3.3.1, 4.1.3 | | |
| 6.3 | Scelta degli articoli (dal dettaglio ordine) | Ogni casella dice il prodotto; quelli personalizzati dicono perché non si possono scegliere | 1.3.1, 3.3.2 | | |
| 6.4 | Modulo compilato, «Continua» | Il focus va al titolo del riepilogo, che viene letto | 2.4.3, 4.1.3 | | |
| 6.5 | «Modifica» | Torna ai dati già compilati, il focus sul primo campo | 2.4.3, 3.3.4 | | |

## 7. Registrazione (`/shop/registrati`)

| # | Azione | Comportamento atteso | WCAG | Esito NVDA | Esito VoiceOver |
| --- | --- | --- | --- | --- | --- |
| 7.1 | Campi | Nome, email, password e conferma hanno un'etichetta letta | 1.3.1, 3.3.2 | | |
| 7.2 | Requisiti della password | Si sentono prima di scrivere, non solo dopo l'errore | 3.3.2 | | |
| 7.3 | «Mostra password» | Il pulsante dice cosa fa e se la password è visibile | 4.1.2 | | |
| 7.4 | Invio a modulo vuoto | Ogni campo dice «non valido» con il suo messaggio, collegato al campo; il focus va al primo errore | 3.3.1, 3.3.3, 4.1.3 | | |
| 7.5 | Frase sull'informativa privacy | Si legge come testo con il link «l'informativa privacy»; non c'è nessuna casella da spuntare; il link dice che si apre in una nuova scheda o lo si capisce | 2.4.4, 3.2.5 | | |
| 7.6 | Registrazione riuscita | Il messaggio di conferma viene letto | 4.1.3 | | |

---

## Esito

| Difetto | Passo | Screen reader | Gravità (blocca / ostacola / fastidio) | Note |
| --- | --- | --- | --- | --- |
| | | | | |

Consegnare la tabella compilata a chi segue il sito: i difetti si correggono o
diventano limiti noti della dichiarazione, come detto in apertura.
