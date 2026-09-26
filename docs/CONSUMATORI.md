# Shop e consumatori: la mappa tecnica

Come `docs/PRIVACY.md` per i dati personali, questo file dice **dove** il
negozio rispetta gli obblighi verso i consumatori (Codice del consumo, d.lgs.
206/2005) e dove non ancora. Non è un parere legale: è quello che va fatto
leggere a chi segue gli aspetti legali della società.

Ultima verifica sul codice: **26 settembre 2026**.

---

## 1. Cosa fa il codice, obbligo per obbligo

| Obbligo | Dove si vede | Dove sta nel codice |
| --- | --- | --- |
| Condizioni di vendita e informativa sul recesso prima dell'ordine (art. 49) | pagine `/condizioni-di-vendita` e `/diritto-di-recesso`, link nel footer, nella casella del checkout e nel "Reso entro 14 giorni" della scheda prodotto | testi in `database/data/condizioni_di_vendita.php`, letti da `App\Support\CondizioniDiVendita` |
| Modulo tipo di recesso (Allegato I, parte B) | in fondo a `/diritto-di-recesso` e nell'email di conferma | stesso file dati; `resources/views/emails/partials/informazioni-contrattuali.blade.php` |
| Pulsante «Ordine con obbligo di pagamento» (art. 51 c. 2) | checkout dello shop e delle aste, seconda riga del pulsante | `resources/js/Components/Shop/PulsanteOrdine.vue` |
| Accettazione delle condizioni, con la versione e il testo esatto | casella del checkout | `AccettazioneCondizioni.vue`; `orders.condizioni_versione` = `CondizioniDiVendita::VERSIONE`; `orders.condizioni_impronta` = sha256 del testo pubblicato, testo in `versioni_condizioni` (`CondizioniDiVendita::registraIstantanea`) |
| Conferma su supporto durevole (art. 51 c. 7) | email di conferma: venditore, recesso, modulo, garanzia, e il PDF delle condizioni in allegato | `App\Mail\OrderConfirmation::attachments()`, `resources/views/pdf/condizioni-di-vendita.blade.php` |
| Funzione di recesso online (art. 54-bis, dal 19/06/2026) | `/recesso` (`/en/withdrawal`; `/en/recesso` è un 301), dal footer, dal dettaglio ordine e dall'email di conferma: due passaggi, ricevuta su un indirizzo firmato (stampabile) e per email subito. La dichiarazione si registra sempre; oltre tre al giorno per indirizzo si salta solo l'email della ricevuta. Se l'email non è quella dell'ordine, al titolare va un avviso senza i dati del dichiarante (al massimo uno al giorno per ordine) e il pannello lo segnala. Chi arriva col token dell'ordine (dettaglio, email) o dal proprio account sceglie gli articoli da una lista | `RecessoController`, tabella `richieste_di_recesso`, risorsa Filament «Richieste di recesso» (non si cancellano) |
| Pagine pratiche: spedizioni (con la tabella delle zone), resi e rimborsi, regolamento aste | `/spedizioni`, `/resi-e-rimborsi`, `/regolamento-aste`; i vecchi indirizzi WooCommerce ci portano con un 301 | `database/data/condizioni_shop.php`, `App\Support\PagineLegaliDelloShop`, `routes/pubbliche/legacy.php` |
| Avviso armonizzato UE sulla garanzia legale | sotto la casella del checkout, nella scheda prodotto e nell'email di conferma | `AvvisoGaranziaLegale.vue`, `public/images/garanzia/` (pagina 1 dei PDF della Commissione) |
| Beni personalizzati esclusi dal recesso (art. 59 c. 1 lett. c) | la firma della giocatrice: avviso accanto alla casella nella scheda prodotto, segno sulla riga nel checkout e nell'email di conferma, righe non selezionabili in `/recesso`; condizioni, informativa sul recesso e "Resi e rimborsi" la nominano | `order_items.personalizzazione`; `RecessoController::articoliScelti` rifiuta le righe personalizzate; `resources/js/Support/righeDiRecesso.js` |
| Prezzo precedente negli sconti (art. 17-bis, Omnibus) | il barrato è il prezzo più basso dei 30 giorni prima della riduzione, con la didascalia | `App\Services\StoricoPrezzi`, tabella `storico_prezzi`, `ShopController::prezzi()`; `prezzi:registra` ogni ora |

### Le versioni delle condizioni

`CondizioniDiVendita::VERSIONE` è la data della versione in vigore e finisce
su ogni ordine. **Si alza quando cambia la sostanza** (recesso, garanzia,
pagamenti, consegna), non a ogni ritocco. È un'etichetta leggibile, non la
prova: la redazione modifica le pagine dal pannello senza toccare il codice.

**La prova è l'istantanea (dal 26/09/2026).** Alla creazione dell'ordine,
shop e aste, `CondizioniDiVendita::registraIstantanea()` prende il testo
**pubblicato** delle due pagine nella lingua dell'ordine (il cliente accetta
quello che legge; il file dati solo se la pagina manca o è vuota), con i
link resi assoluti, e ne salva lo sha256 su `orders.condizioni_impronta`. Il
testo sta una volta sola in `versioni_condizioni` (impronta unica,
`insertOrIgnore`): finché nessuno tocca le pagine, tutti gli ordini puntano
alla stessa riga. Il PDF allegato alla conferma si genera da quella riga
(`perLAllegatoDellOrdine`), non dalla pagina com'è al momento dell'invio; gli
ordini senza impronta, nati prima, ripiegano sul testo corrente. Le righe di
`versioni_condizioni` non si cancellano: sono la prova del contratto per
tutto il tempo in cui si conserva l'ordine.

Le pagine si creano solo se mancano (`creaLePagineMancanti`): una pagina con
lo stesso slug scritta dalla redazione vince sempre.

### Il prezzo barrato

- Si registra il **prezzo effettivo** (quello che il carrello fa pagare), non
  le colonne del prodotto. Ogni riga dice se era uno sconto (`in_sconto`).
- La riduzione comincia dove comincia la serie ininterrotta di righe in
  sconto: in una riduzione progressiva vale il prezzo prima della **prima**.
- **Un prodotto senza prezzo pieno praticato prima dello sconto non barra
  niente**: si vende al prezzo scontato, senza l'annuncio. Il 25 settembre
  2026 era il caso delle maglie gara 2025/2026 (id 78 e 79), create e
  scontate lo stesso giorno.
- Lo storico è partito il 25 settembre 2026. Per i prodotti già scontati
  quel giorno (id 3, 15, 19) la migrazione ha ricostruito il prezzo pieno fra
  la creazione e l'ultima modifica: l'export di WooCommerce del 4 luglio non
  aveva sconti su di loro, quindi lo sconto è arrivato dopo l'import.

---

## 2. Punti aperti (decisioni della società)

1. **Spese di restituzione (deciso il 26/09/2026).** A carico del cliente,
   come dice il testo: è la regola di legge quando il venditore non dice
   altro (art. 57 c. 1).
2. **Prodotti personalizzati esclusi dal recesso (risolto il 26/09/2026).**
   La personalizzazione che il negozio vende è la firma della giocatrice,
   fotografata su `order_items.personalizzazione`: quelle righe sono escluse
   (tabella del §1). Un articolo interamente su misura, fuori da questo
   meccanismo, resta da gestire a mano: non c'è un campo di prodotto che lo
   dica. Nota: che una firma aggiunta scegliendo un'opzione del catalogo sia
   un bene «chiaramente personalizzato» è la lettura della società, non un
   dato pacifico — da far confermare a chi segue gli aspetti legali.
3. **Casella per il recesso (deciso il 26/09/2026).** Resta
   `info@savinodelbenevolley.it` (e la PEC).
4. **Garanzia sulle maglie da gara e sugli autografati.** Il testo li vende
   «nello stato descritto nella scheda»: la scheda deve quindi descrivere lo
   stato (usata, segni di gara) perché la clausola regga.
5. **Dati societari (risolto il 25/09/2026).** Dalla visura del 07/08/2025:
   REA FI-624279, Registro Imprese di Firenze con il numero del codice
   fiscale, capitale € 150.000,00 versato. Stanno nelle impostazioni del
   gruppo `contact` (`legal_rea`, `legal_capitale`, `legal_ragione_sociale`).
6. **Corriere**: resta «corriere tracciato»; il vecchio shop diceva Bartolini.
7. **Conservazione delle dichiarazioni di recesso (deciso il 26/09/2026):**
   10 anni dall'invio quando la dichiarazione è agganciata a un ordine
   (`order_id` valorizzato): è la prova del recesso, e il diritto al rimborso
   si prescrive in 10 anni (art. 2946 c.c.). 12 mesi quando il numero scritto
   nel modulo non corrisponde a nessun ordine. Dichiarato nell'informativa e
   applicato da `model:prune` (`RichiestaDiRecesso::prunable`).
8. **La newsletter richiede la conferma per email**; le richieste mai
   confermate si cancellano dopo 30 giorni (`model:prune`).
9. **La posta esce dal 25/09/2026** (Resend, vedi `docs/GO_LIVE.md` §1): le
   conferme d'ordine con il PDF e le ricevute di recesso partono davvero.
