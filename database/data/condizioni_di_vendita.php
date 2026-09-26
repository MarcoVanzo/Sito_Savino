<?php

/**
 * Condizioni di vendita e informativa sul diritto di recesso dello shop.
 *
 * Stanno qui per la stessa ragione delle informative privacy
 * (`informative_privacy.php`): sono contenuti che la redazione può riscrivere
 * dal pannello, e le migrazioni li applicano una volta sola e a guardia. Li
 * leggono `App\Support\CondizioniDiVendita`, il seeder delle pagine e la
 * migrazione che crea le due pagine in produzione.
 *
 * Fino al 25 settembre 2026 lo shop vendeva senza nessuna delle due. Non era
 * una mancanza di forma: senza l'informativa sul recesso il termine per
 * recedere non è di 14 giorni ma si allunga di dodici mesi (art. 53 del Codice
 * del consumo), e la scheda prodotto prometteva un «Reso Facile» che nessuna
 * regola scritta definiva.
 *
 * L'informativa sul recesso segue il modello dell'Allegato I, parte A, del
 * Codice del consumo, e il modulo quello della parte B: usare il modello
 * compilato correttamente è ciò che l'art. 49 c. 4 considera adempimento
 * dell'obbligo di informazione, quindi non va parafrasato.
 *
 * Due scelte sono commerciali e non legali, e le ha fatte il codice in attesa
 * che la società le confermi (sono fra i punti aperti di `docs/CONSUMATORI.md`):
 *
 * - le spese di restituzione sono a carico del cliente, come prevede la legge
 *   quando il venditore non dice altro (art. 57 c. 1);
 * - i prodotti personalizzati su richiesta (nome e numero stampati) sono
 *   esclusi dal recesso, come consente l'art. 59 c. 1 lett. c.
 *
 * Il titolare e i recapiti sono quelli delle informative (§21 del CLAUDE.md):
 * la ragione sociale per esteso, non il nome d'uso della squadra.
 *
 * NON è un parere legale: è un testo costruito sui modelli di legge e su quello
 * che il negozio fa davvero, da far leggere a chi segue gli aspetti legali della
 * società.
 */
$societa = 'Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata';
$sede = 'Via Benozzo Gozzoli, 5/6 — 50018 Scandicci (FI)';
$email = 'info@savinodelbenevolley.it';
$pec = 'pallavoloscandicci@legalmail.it';
$piva = '06271460484';
$cf = '94217750481';

return [
    'condizioni-di-vendita' => [
        'titolo' => ['it' => 'Condizioni di vendita', 'en' => 'Terms of sale'],
        'meta_description' => [
            'it' => 'Condizioni generali di vendita dello shop ufficiale della Savino Del Bene Volley: ordini, prezzi, pagamenti, consegna, recesso e garanzia.',
            'en' => 'General terms of sale of the Savino Del Bene Volley official shop: orders, prices, payments, delivery, withdrawal and warranty.',
        ],
        // Nessuna versione precedente: sono il primo testo pubblicato.
        'firme' => [],
        'contenuto' => [
            'it' => <<<HTML
            <h2>Condizioni generali di vendita</h2>
            <p>Queste condizioni regolano gli acquisti fatti sullo shop ufficiale della Savino Del Bene Volley, compresi i beni aggiudicati nelle aste online. Sono aggiornate al 25 settembre 2026. Ti chiediamo di leggerle prima di ordinare: al momento dell'ordine ti viene chiesto di accettarle, e te le mandiamo di nuovo con la conferma.</p>

            <h3>1. Chi vende</h3>
            <p>{$societa}<br />
            {$sede}<br />
            P. IVA {$piva} — C.F. e n. di iscrizione al Registro delle Imprese di Firenze {$cf} — REA FI-624279 — capitale sociale € 150.000,00 interamente versato<br />
            Email: <a href="mailto:{$email}">{$email}</a> — PEC: <a href="mailto:{$pec}">{$pec}</a> — Telefono: +39 055 721503 (lunedì-venerdì, 9-18)</p>

            <h3>2. Come si conclude il contratto</h3>
            <p>Scegli i prodotti, inserisci i dati di spedizione e il metodo di pagamento, e confermi con il pulsante <strong>«Ordine con obbligo di pagamento»</strong>. Prima di premerlo vedi il riepilogo dell'ordine con il prezzo totale, le spese di spedizione e i tempi di consegna stimati, e puoi correggere i dati inseriti.</p>
            <p>Il contratto è concluso quando ricevi l'email di conferma dell'ordine, che riporta il riepilogo, queste condizioni in sintesi e l'informativa sul diritto di recesso con il relativo modulo.</p>
            <p>Per le aste il contratto si conclude con l'aggiudicazione: il vincitore riceve un'email con il collegamento per completare il pagamento entro il termine indicato. Se il pagamento non arriva entro quel termine l'aggiudicazione decade e il bene può essere assegnato al miglior offerente successivo.</p>

            <h3>3. Prezzi</h3>
            <p>I prezzi sono in euro e comprendono l'IVA. Le spese di spedizione dipendono dal paese di destinazione e dal peso dell'ordine, e sono mostrate prima della conferma. Quando un prodotto è in promozione, accanto al prezzo scontato indichiamo il prezzo più basso applicato nei 30 giorni precedenti la riduzione, come prevede l'art. 17-bis del Codice del consumo.</p>

            <h3>4. Pagamento</h3>
            <p>Puoi pagare con i metodi mostrati al checkout: PayPal, carta di pagamento (quando disponibile) e bonifico bancario. Con il bonifico l'ordine resta in attesa fino all'accredito e viene annullato se il pagamento non arriva entro il termine indicato nell'email di conferma. Non conserviamo i dati delle carte: il pagamento avviene sulle pagine del gestore.</p>

            <h3>5. Consegna</h3>
            <p>Spediamo con corriere tracciato nei paesi elencati al checkout. I tempi stimati sono indicati prima della conferma e decorrono dalla ricezione del pagamento; in ogni caso consegniamo entro 30 giorni dalla conclusione del contratto (art. 61 del Codice del consumo). Costi e tempi per ogni zona sono nella pagina <a href="/spedizioni">Spedizioni</a>. Il rischio di perdita o danneggiamento passa a te solo quando ricevi fisicamente i beni (art. 63).</p>
            <p>Quando ricevi il pacco controlla che sia integro: se è danneggiato, accettalo con riserva scritta sul documento del corriere e scrivici.</p>

            <h3>6. Diritto di recesso</h3>
            <p>Hai 14 giorni dalla consegna per recedere dal contratto senza indicarne le ragioni. Puoi farlo direttamente dal sito con la funzione <a href="/recesso">Recedi dal contratto</a>, raggiungibile dal fondo di ogni pagina e dal dettaglio dell'ordine, che ti manda subito una ricevuta con data e ora. Le modalità, i costi della restituzione, i tempi del rimborso e il modulo tipo sono nella pagina <a href="/diritto-di-recesso">Diritto di recesso</a>; come rispedire i prodotti è spiegato in <a href="/resi-e-rimborsi">Resi e rimborsi</a>.</p>
            <p>Il recesso non si applica ai prodotti confezionati su misura o chiaramente personalizzati su tua richiesta, per esempio una maglia con nome e numero scelti da te (art. 59 c. 1 lett. c). Resta invece valido per i beni aggiudicati nelle aste online.</p>

            <h3>7. Garanzia legale di conformità</h3>
            <p>Tutti i prodotti sono coperti dalla garanzia legale di conformità prevista dagli articoli 128 e seguenti del Codice del consumo: rispondiamo dei difetti di conformità che si manifestano entro <strong>due anni dalla consegna</strong>. Se il prodotto è difettoso hai diritto, senza spese, al ripristino della conformità con la riparazione o la sostituzione, oppure, se queste non sono possibili, a una riduzione del prezzo o alla risoluzione del contratto. Per farla valere scrivi a <a href="mailto:{$email}">{$email}</a> indicando il numero d'ordine e descrivendo il difetto, se possibile con una fotografia. Le informazioni ufficiali dell'Unione europea sui tuoi diritti di garanzia sono su <a href="https://europa.eu/youreurope/garanzie">europa.eu/youreurope/garanzie</a>.</p>
            <p>La garanzia non copre l'usura normale né i danni dovuti a un uso o a un lavaggio diversi da quelli indicati in etichetta. I prodotti autografati e le maglie da gara sono venduti nello stato descritto nella scheda alla voce «Stato dell'articolo», riportato anche nella conferma d'ordine.</p>

            <h3>8. Assistenza e reclami</h3>
            <p>Per qualsiasi domanda o reclamo scrivi a <a href="mailto:{$email}">{$email}</a> o alla PEC <a href="mailto:{$pec}">{$pec}</a>: rispondiamo entro 10 giorni lavorativi.</p>

            <h3>9. Dati personali</h3>
            <p>I dati che ci dai per l'ordine sono trattati come descritto nella <a href="/privacy-policy">Privacy Policy</a>.</p>

            <h3>10. Legge applicabile e foro</h3>
            <p>Il contratto è regolato dalla legge italiana. Restano salve le tutele più favorevoli che ti riconosce la legge del paese in cui risiedi abitualmente. Per le controversie è competente il giudice del luogo in cui risiedi o hai il domicilio, se sei un consumatore in Italia.</p>
            HTML,
            'en' => <<<HTML
            <h2>General terms of sale</h2>
            <p>These terms govern purchases made on the official Savino Del Bene Volley shop, including items won in online auctions. Last updated 25 September 2026. Please read them before ordering: you are asked to accept them when you place the order, and we send them to you again with the confirmation.</p>

            <h3>1. The seller</h3>
            <p>{$societa}<br />
            {$sede}, Italy<br />
            VAT {$piva} — Tax code and Florence Companies Register number {$cf} — REA FI-624279 — share capital € 150,000.00 fully paid up<br />
            Email: <a href="mailto:{$email}">{$email}</a> — Certified email: <a href="mailto:{$pec}">{$pec}</a> — Phone: +39 055 721503 (Monday-Friday, 9-18 CET)</p>

            <h3>2. How the contract is formed</h3>
            <p>You choose the products, enter your delivery details and payment method, and confirm with the <strong>"Order with obligation to pay"</strong> button. Before pressing it you see the order summary with the total price, shipping costs and estimated delivery time, and you can correct the data entered.</p>
            <p>The contract is concluded when you receive the order confirmation email, which contains the summary, a summary of these terms and the information on the right of withdrawal with the withdrawal form.</p>
            <p>For auctions the contract is concluded when the item is awarded: the winner receives an email with a link to complete payment within the stated deadline. If payment is not received in time, the award lapses and the item may be offered to the next highest bidder.</p>

            <h3>3. Prices</h3>
            <p>Prices are in euro and include VAT. Shipping costs depend on the destination country and the weight of the order, and are shown before confirmation. When a product is on sale, next to the reduced price we show the lowest price applied in the 30 days before the reduction, as required by article 17-bis of the Italian Consumer Code.</p>

            <h3>4. Payment</h3>
            <p>You can pay with the methods shown at checkout: PayPal, card (when available) and bank transfer. With a bank transfer the order remains pending until the payment is credited and is cancelled if payment is not received within the deadline stated in the confirmation email. We do not store card details: payment takes place on the provider's pages.</p>

            <h3>5. Delivery</h3>
            <p>We ship with a tracked courier to the countries listed at checkout. Estimated times are shown before confirmation and run from receipt of payment; in any case we deliver within 30 days of the conclusion of the contract. Costs and times for each area are on the <a href="/en/spedizioni">Shipping</a> page. The risk of loss or damage passes to you only when you physically receive the goods.</p>
            <p>When you receive the parcel, check that it is intact: if it is damaged, accept it with a written reservation on the courier's document and write to us.</p>

            <h3>6. Right of withdrawal</h3>
            <p>You have 14 days from delivery to withdraw from the contract without giving any reason. You can do it directly on the site with the <a href="/en/recesso">Withdraw from contract</a> function, available at the bottom of every page and in the order details, which immediately sends you a receipt with the date and time. How to do it, return costs, refund times and the model form are on the <a href="/en/diritto-di-recesso">Right of withdrawal</a> page; how to send the products back is explained in <a href="/en/resi-e-rimborsi">Returns and refunds</a>.</p>
            <p>The right of withdrawal does not apply to goods made to your specifications or clearly personalised at your request, for example a shirt with a name and number chosen by you. It does apply to items won in online auctions.</p>

            <h3>7. Legal guarantee of conformity</h3>
            <p>All products are covered by the legal guarantee of conformity under articles 128 et seq. of the Italian Consumer Code: we are liable for any lack of conformity that becomes apparent within <strong>two years of delivery</strong>. If the product is defective you are entitled, free of charge, to have it brought into conformity by repair or replacement or, where that is not possible, to a price reduction or to terminate the contract. To make a claim write to <a href="mailto:{$email}">{$email}</a> with your order number and a description of the defect, with a photo if possible. Official European Union information on your guarantee rights is at <a href="https://europa.eu/youreurope/guarantees">europa.eu/youreurope/guarantees</a>.</p>
            <p>The guarantee does not cover normal wear and tear or damage caused by use or washing other than as shown on the label. Signed products and match shirts are sold in the condition described under “Item condition” on the product page, which is also shown in the order confirmation.</p>

            <h3>8. Customer service and complaints</h3>
            <p>For any question or complaint write to <a href="mailto:{$email}">{$email}</a> or to the certified email <a href="mailto:{$pec}">{$pec}</a>: we reply within 10 working days.</p>

            <h3>9. Personal data</h3>
            <p>The data you give us for the order are processed as described in the <a href="/en/privacy-policy">Privacy Policy</a>.</p>

            <h3>10. Governing law and jurisdiction</h3>
            <p>The contract is governed by Italian law, without prejudice to any more favourable protection granted to you by the law of the country where you habitually reside. If you are a consumer, the courts of the place where you reside or are domiciled have jurisdiction.</p>
            HTML,
        ],
    ],

    'diritto-di-recesso' => [
        'titolo' => ['it' => 'Diritto di recesso', 'en' => 'Right of withdrawal'],
        'meta_description' => [
            'it' => 'Informativa sul diritto di recesso dello shop della Savino Del Bene Volley e modulo tipo di recesso.',
            'en' => 'Information on the right of withdrawal for the Savino Del Bene Volley shop and model withdrawal form.',
        ],
        'firme' => [],
        'contenuto' => [
            'it' => <<<HTML
            <h2>Informativa sul diritto di recesso</h2>
            <p>Aggiornata al 25 settembre 2026. Vale per gli acquisti fatti come consumatore sullo shop ufficiale, compresi i beni aggiudicati nelle aste online.</p>

            <h3>Diritto di recesso</h3>
            <p>Hai il diritto di recedere dal contratto, senza indicarne le ragioni, entro 14 giorni.</p>
            <p>Il periodo di recesso scade dopo 14 giorni dal giorno in cui tu, o un terzo da te designato diverso dal vettore, acquisisci il possesso fisico dei beni. Se hai ordinato più beni con un solo ordine e li ricevi separatamente, i 14 giorni decorrono dal giorno in cui ricevi l'ultimo.</p>
            <p>Il modo più semplice è la funzione online <a href="/recesso">Recedi dal contratto</a>: inserisci nome, numero d'ordine ed email, confermi con il pulsante «Conferma recesso» e ricevi subito via email una ricevuta con il contenuto della dichiarazione, la data e l'ora dell'invio (art. 54-bis del Codice del consumo).</p>
            <p>In alternativa puoi informarci della tua decisione con una dichiarazione esplicita, per esempio una lettera inviata per posta o un'email, a:</p>
            <p>{$societa}<br />
            {$sede}<br />
            Email: <a href="mailto:{$email}">{$email}</a> — PEC: <a href="mailto:{$pec}">{$pec}</a></p>
            <p>Puoi usare il modulo tipo che trovi più sotto, ma non è obbligatorio. Per rispettare il termine basta che tu ci invii la comunicazione prima della scadenza del periodo di recesso.</p>

            <h3>Effetti del recesso</h3>
            <p>Se recedi dal contratto ti rimborsiamo tutti i pagamenti che hai effettuato a nostro favore, compresi i costi di consegna (esclusi gli eventuali costi supplementari dovuti alla scelta di un tipo di consegna diverso da quello meno costoso che offriamo), senza indebito ritardo e comunque entro 14 giorni dal giorno in cui siamo informati della tua decisione. Il rimborso avviene con lo stesso mezzo di pagamento che hai usato, salvo che tu abbia espressamente convenuto altrimenti; in ogni caso non ti costa nulla.</p>
            <p>Possiamo trattenere il rimborso finché non abbiamo ricevuto i beni oppure finché non ci dimostri di averli rispediti, se questo avviene prima.</p>
            <p>Devi rispedire i beni, o consegnarli a mano presso la nostra sede, senza indebiti ritardi e comunque entro 14 giorni dal giorno in cui ci hai comunicato il recesso. Il termine è rispettato se rispedisci i beni prima della scadenza dei 14 giorni.</p>
            <p><strong>I costi diretti della restituzione dei beni sono a tuo carico.</strong></p>
            <p>Sei responsabile solo della diminuzione del valore dei beni che risulti da una manipolazione diversa da quella necessaria per stabilirne la natura, le caratteristiche e il funzionamento: puoi provare una maglia come faresti in negozio, ma se la indossi, la lavi o togli le etichette il rimborso può essere ridotto.</p>

            <h3>Quando il recesso non si applica</h3>
            <p>Il diritto di recesso è escluso per i beni confezionati su misura o chiaramente personalizzati su tua richiesta, per esempio una maglia con nome e numero scelti da te (art. 59 c. 1 lett. c del Codice del consumo).</p>

            <h3>Recesso e garanzia sono due cose diverse</h3>
            <p>Il recesso non richiede motivazioni. Se invece il prodotto che hai ricevuto è difettoso o non corrisponde a quello ordinato, vale la garanzia legale di conformità di due anni, senza costi per te: è descritta nelle <a href="/condizioni-di-vendita">Condizioni di vendita</a>.</p>

            <h3>Modulo tipo di recesso</h3>
            <p>Compila e restituisci questo modulo solo se desideri recedere dal contratto. Puoi copiarlo in un'email.</p>
            <blockquote>
            <p>Destinatario: {$societa}, {$sede}, <a href="mailto:{$email}">{$email}</a></p>
            <p>Con la presente io/noi (*) notifico/notifichiamo (*) il recesso dal mio/nostro (*) contratto di vendita dei seguenti beni:</p>
            <p>……………………………………………………</p>
            <p>Numero d'ordine: ……………………</p>
            <p>Ordinato il (*) / ricevuto il (*): ……………………</p>
            <p>Nome del/dei consumatore/i: ……………………</p>
            <p>Indirizzo del/dei consumatore/i: ……………………</p>
            <p>Firma del/dei consumatore/i (solo se il presente modulo è notificato in versione cartacea): ……………………</p>
            <p>Data: ……………………</p>
            <p>(*) Cancellare la dicitura inutile.</p>
            </blockquote>
            HTML,
            'en' => <<<HTML
            <h2>Information on the right of withdrawal</h2>
            <p>Last updated 25 September 2026. It applies to purchases made as a consumer on the official shop, including items won in online auctions.</p>

            <h3>Right of withdrawal</h3>
            <p>You have the right to withdraw from this contract within 14 days without giving any reason.</p>
            <p>The withdrawal period expires 14 days after the day on which you, or a third party other than the carrier and indicated by you, acquire physical possession of the goods. If you ordered multiple goods in one order and they are delivered separately, the 14 days run from the day you receive the last one.</p>
            <p>The easiest way is the online <a href="/en/recesso">Withdraw from contract</a> function: enter your name, order number and email, confirm with the "Confirm withdrawal" button and you immediately receive by email a receipt with the content of your statement and the date and time it was sent.</p>
            <p>Alternatively you can inform us of your decision by an unequivocal statement, for example a letter sent by post or an email, to:</p>
            <p>{$societa}<br />
            {$sede}, Italy<br />
            Email: <a href="mailto:{$email}">{$email}</a> — Certified email: <a href="mailto:{$pec}">{$pec}</a></p>
            <p>You may use the model withdrawal form below, but it is not obligatory. To meet the withdrawal deadline, it is sufficient for you to send your communication before the withdrawal period has expired.</p>

            <h3>Effects of withdrawal</h3>
            <p>If you withdraw from this contract, we shall reimburse to you all payments received from you, including the costs of delivery (with the exception of any supplementary costs resulting from your choice of a type of delivery other than the least expensive standard delivery offered by us), without undue delay and in any event not later than 14 days from the day on which we are informed about your decision. We will carry out the reimbursement using the same means of payment you used, unless you have expressly agreed otherwise; in any event, you will not incur any fees.</p>
            <p>We may withhold reimbursement until we have received the goods back or you have supplied evidence of having sent them back, whichever is the earliest.</p>
            <p>You shall send back the goods, or hand them over at our premises, without undue delay and in any event not later than 14 days from the day on which you communicate your withdrawal. The deadline is met if you send back the goods before the period of 14 days has expired.</p>
            <p><strong>You will have to bear the direct cost of returning the goods.</strong></p>
            <p>You are only liable for any diminished value of the goods resulting from handling other than what is necessary to establish their nature, characteristics and functioning: you can try on a shirt as you would in a shop, but if you wear it, wash it or remove the labels the refund may be reduced.</p>

            <h3>When withdrawal does not apply</h3>
            <p>The right of withdrawal does not apply to goods made to your specifications or clearly personalised at your request, for example a shirt with a name and number chosen by you.</p>

            <h3>Withdrawal and warranty are different things</h3>
            <p>Withdrawal requires no reason. If the product you received is faulty or does not match your order, the two-year legal guarantee of conformity applies at no cost to you: it is described in the <a href="/en/condizioni-di-vendita">Terms of sale</a>.</p>

            <h3>Model withdrawal form</h3>
            <p>Complete and return this form only if you wish to withdraw from the contract. You can copy it into an email.</p>
            <blockquote>
            <p>To: {$societa}, {$sede}, Italy, <a href="mailto:{$email}">{$email}</a></p>
            <p>I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods:</p>
            <p>……………………………………………………</p>
            <p>Order number: ……………………</p>
            <p>Ordered on (*) / received on (*): ……………………</p>
            <p>Name of consumer(s): ……………………</p>
            <p>Address of consumer(s): ……………………</p>
            <p>Signature of consumer(s) (only if this form is notified on paper): ……………………</p>
            <p>Date: ……………………</p>
            <p>(*) Delete as appropriate.</p>
            </blockquote>
            HTML,
        ],
    ],
];
