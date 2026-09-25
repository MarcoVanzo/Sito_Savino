# Shop e consumatori: la mappa tecnica

Come `docs/PRIVACY.md` per i dati personali, questo file dice **dove** il
negozio rispetta gli obblighi verso i consumatori (Codice del consumo, d.lgs.
206/2005) e dove non ancora. Non è un parere legale: è quello che va fatto
leggere a chi segue gli aspetti legali della società.

Ultima verifica sul codice: **25 settembre 2026**.

---

## 1. Cosa fa il codice, obbligo per obbligo

| Obbligo | Dove si vede | Dove sta nel codice |
| --- | --- | --- |
| Condizioni di vendita e informativa sul recesso prima dell'ordine (art. 49) | pagine `/condizioni-di-vendita` e `/diritto-di-recesso`, link nel footer, nella casella del checkout e nel "Reso entro 14 giorni" della scheda prodotto | testi in `database/data/condizioni_di_vendita.php`, letti da `App\Support\CondizioniDiVendita` |
| Modulo tipo di recesso (Allegato I, parte B) | in fondo a `/diritto-di-recesso` e nell'email di conferma | stesso file dati; `resources/views/emails/partials/informazioni-contrattuali.blade.php` |
| Pulsante «Ordine con obbligo di pagamento» (art. 51 c. 2) | checkout dello shop e delle aste, seconda riga del pulsante | `resources/js/Components/Shop/PulsanteOrdine.vue` |
| Accettazione delle condizioni, con la versione | casella del checkout | `AccettazioneCondizioni.vue`; `orders.condizioni_versione` = `CondizioniDiVendita::VERSIONE` |
| Conferma su supporto durevole (art. 51 c. 7) | email di conferma: venditore, recesso, modulo, garanzia, e il PDF delle condizioni in allegato | `App\Mail\OrderConfirmation::attachments()`, `resources/views/pdf/condizioni-di-vendita.blade.php` |
| Funzione di recesso online (art. 54-bis, dal 19/06/2026) | `/recesso`, dal footer e dal dettaglio ordine: due passaggi, ricevuta a schermo e per email subito | `RecessoController`, tabella `richieste_di_recesso`, risorsa Filament «Richieste di recesso» (non si cancellano) |
| Pagine pratiche: spedizioni (con la tabella delle zone), resi e rimborsi, regolamento aste | `/spedizioni`, `/resi-e-rimborsi`, `/regolamento-aste`; i vecchi indirizzi WooCommerce ci portano con un 301 | `database/data/condizioni_shop.php`, `App\Support\PagineLegaliDelloShop`, `routes/pubbliche/legacy.php` |
| Avviso armonizzato UE sulla garanzia legale | sotto la casella del checkout, nella scheda prodotto e nell'email di conferma | `AvvisoGaranziaLegale.vue`, `public/images/garanzia/` (pagina 1 dei PDF della Commissione) |
| Prezzo precedente negli sconti (art. 17-bis, Omnibus) | il barrato è il prezzo più basso dei 30 giorni prima della riduzione, con la didascalia | `App\Services\StoricoPrezzi`, tabella `storico_prezzi`, `ShopController::prezzi()`; `prezzi:registra` ogni ora |

### Le versioni delle condizioni

`CondizioniDiVendita::VERSIONE` è la data della versione in vigore e finisce
su ogni ordine. **Si alza quando cambia la sostanza** (recesso, garanzia,
pagamenti, consegna), non a ogni ritocco. Il PDF allegato alla conferma è il
testo del file dati, non quello della pagina: se la redazione riscrive la
pagina dal pannello, il file dati va aggiornato con lo stesso testo, o
l'allegato continuerà a mandare il vecchio.

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

1. **Spese di restituzione.** Il testo le mette a carico del cliente, che è
   la regola di legge quando il venditore non dice altro. Se la società vuole
   il reso gratuito, va cambiata una frase nel file dati e nell'email.
2. **Prodotti personalizzati esclusi dal recesso.** Oggi nel catalogo non c'è
   un campo che dica se un articolo è personalizzato: l'esclusione vale per
   quello che il cliente ha chiesto di personalizzare, e va gestita a mano.
3. **Casella per il recesso.** Il testo indica `info@savinodelbenevolley.it`
   (e la PEC). Se esiste una casella dedicata allo shop, va sostituita nel
   file dati e in `CondizioniDiVendita::venditore()`.
4. **Garanzia sulle maglie da gara e sugli autografati.** Il testo li vende
   «nello stato descritto nella scheda»: la scheda deve quindi descrivere lo
   stato (usata, segni di gara) perché la clausola regga.
5. **Dati societari (risolto il 25/09/2026).** Dalla visura del 07/08/2025:
   REA FI-624279, Registro Imprese di Firenze con il numero del codice
   fiscale, capitale € 150.000,00 versato. Stanno nelle impostazioni del
   gruppo `contact` (`legal_rea`, `legal_capitale`, `legal_ragione_sociale`).
6. **Corriere**: resta «corriere tracciato»; il vecchio shop diceva Bartolini.
7. **Conservazione delle dichiarazioni di recesso (deciso il 25/09/2026):**
   12 mesi dall'invio, dichiarati nell'informativa e applicati da
   `model:prune`. Il rimborso resta registrato sull'ordine.
8. **La newsletter richiede la conferma per email**: finché la posta non esce
   nessuno riesce a completare un'iscrizione nuova.
9. **La posta non esce** finché `MAIL_MAILER` non è configurato (vedi
   `docs/GO_LIVE.md`): tutto quello che questo file dice dell'email di
   conferma oggi finisce nel log.
