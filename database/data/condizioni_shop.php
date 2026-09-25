<?php

/**
 * Le pagine pratiche dello shop: spedizioni, resi e regolamento delle aste.
 *
 * Condizioni di vendita e informativa sul recesso stanno invece in
 * `condizioni_di_vendita.php` (App\Support\CondizioniDiVendita), perche'
 * finiscono anche nel PDF allegato alla conferma d'ordine e hanno una versione
 * scritta sull'ordine. Queste tre no: spiegano come si fa, e i numeri delle
 * spedizioni arrivano dalle zone del pannello.
 *
 * Il punto di partenza sono i testi del vecchio negozio WooCommerce
 * (shop.savinodelbenevolley.it, letti il 25/09/2026 da `wp-json`: "Condizioni
 * di spedizione", "Informativa sui rimborsi", "Regolamento e privacy policy
 * aste"), riallineati a quello che il sito nuovo fa e alle norme di settembre
 * 2026: recesso anche online (art. 54-bis del Codice del Consumo, dal
 * 19/06/2026) e anche per le aste, che non sono "aste pubbliche" ai sensi
 * dell'art. 45. Il vecchio regolamento aste conteneva anche un'informativa
 * privacy del 2024: qui rimanda alla Privacy Policy del sito.
 *
 * Le pagine si creano una volta sola (migrazione e seeder): da li' in poi
 * sono della redazione, che le modifica dal pannello.
 *
 * @return array<string, array{titolo: array<string, string>, descrizione: array<string, string>, firme: list<string>, contenuto: array<string, string>}>
 */
$italiaIndirizzo = 'Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI)';

return [
    'spedizioni' => [
        'titolo' => ['it' => 'Spedizioni', 'en' => 'Shipping'],
        'descrizione' => [
            'it' => 'Costi, tempi e modalità di spedizione dei prodotti dello Shop ufficiale Savino Del Bene Volley, in Italia e all\'estero.',
            'en' => 'Shipping costs, times and methods for the Savino Del Bene Volley official shop, in Italy and abroad.',
        ],
        'firme' => ['Spediamo con corriere espresso in Italia, nei Paesi europei elencati e nel resto del mondo'],
        'contenuto' => [
            'it' => <<<'HTML'
<p>Spediamo con corriere espresso in Italia, nei Paesi europei elencati e nel resto del mondo. Costi e tempi dipendono dalla zona di consegna e sono riportati nella tabella in fondo alla pagina; il costo esatto del tuo ordine è sempre mostrato nel riepilogo, prima della conferma.</p>
<h2>Tempi di consegna</h2>
<p>Gli ordini pagati con PayPal o carta vengono preparati in 1-2 giorni lavorativi; quelli pagati con bonifico dopo l'accredito. I tempi indicati in tabella sono in giorni lavorativi dalla spedizione e sono indicativi: possono allungarsi nei periodi di festività o per disservizi del corriere. In ogni caso la consegna avviene entro 30 giorni dall'ordine, come previsto dalle <a href="/condizioni-di-vendita">Condizioni di vendita</a>.</p>
<p>Alla spedizione ricevi via email il codice di tracciamento. Il corriere tenta la consegna due volte e poi prova a contattarti: indica un numero di telefono corretto e un indirizzo dove qualcuno possa ritirare il pacco, con il nome presente sul citofono. Non è possibile scegliere il giorno o l'ora della consegna.</p>
<p>Se il pacco arriva visibilmente danneggiato, accettalo "con riserva" annotandolo sul documento del corriere oppure rifiutalo, e scrivici. Se dopo i tempi indicati non hai ancora ricevuto nulla, scrivi a <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> o chiama il +39 055 721503.</p>
<h2>Spedizioni fuori dall'Unione europea</h2>
<p>Per le consegne fuori dall'UE (compresi Regno Unito, Svizzera e Norvegia) possono essere dovuti dazi, IVA locale e oneri di sdoganamento, applicati all'arrivo nel Paese di destinazione. Non sono compresi nel prezzo né nelle spese di spedizione e sono a carico del destinatario: per l'importo rivolgiti alle autorità doganali del tuo Paese.</p>
<h2>Resi</h2>
<p>Per restituire un prodotto consulta la pagina <a href="/resi-e-rimborsi">Resi e rimborsi</a>.</p>
HTML,
            'en' => <<<'HTML'
<p>We ship by express courier to Italy, to the European countries listed and to the rest of the world. Costs and times depend on the delivery area and are shown in the table at the bottom of this page; the exact cost of your order is always shown in the summary before you confirm.</p>
<h2>Delivery times</h2>
<p>Orders paid by PayPal or card are prepared within 1-2 working days; those paid by bank transfer once the payment is received. The times in the table are working days from dispatch and are indicative: they may be longer during holidays or because of courier disruptions. In any case delivery takes place within 30 days of the order, as stated in the <a href="/en/condizioni-di-vendita">Terms of sale</a>.</p>
<p>You receive the tracking code by email on dispatch. The courier attempts delivery twice and then tries to contact you: please give a correct phone number and an address where someone can receive the parcel, with the name shown on the doorbell. Delivery day and time cannot be chosen.</p>
<p>If the parcel arrives visibly damaged, accept it "with reservation" noting this on the courier's document or refuse it, and write to us. If you have not received anything after the times shown, write to <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> or call +39 055 721503.</p>
<h2>Shipping outside the European Union</h2>
<p>For deliveries outside the EU (including the United Kingdom, Switzerland and Norway), customs duties, local VAT and clearance charges may apply on arrival in the destination country. They are not included in the price or in the shipping cost and are paid by the recipient: contact your country's customs authorities for the amount.</p>
<h2>Returns</h2>
<p>To return a product, see the <a href="/en/resi-e-rimborsi">Returns and refunds</a> page.</p>
HTML,
        ],
    ],

    'resi-e-rimborsi' => [
        'titolo' => ['it' => 'Resi e rimborsi', 'en' => 'Returns and refunds'],
        'descrizione' => [
            'it' => 'Come restituire un prodotto dello Shop Savino Del Bene Volley: diritto di recesso entro 14 giorni, rimborso, prodotti difettosi.',
            'en' => 'How to return a product bought on the Savino Del Bene Volley shop: 14-day right of withdrawal, refunds, faulty products.',
        ],
        'firme' => ['Hai cambiato idea? Hai 14 giorni dalla consegna per recedere'],
        'contenuto' => [
            'it' => <<<HTML
<p><strong>Hai cambiato idea? Hai 14 giorni dalla consegna per recedere</strong> dal contratto, senza dover spiegare il motivo. Vale anche per gli oggetti aggiudicati nelle aste online.</p>
<h2>1. Comunica il recesso</h2>
<p>Il modo più semplice è la funzione <a href="/recesso">Recedi dal contratto</a>: inserisci nome, numero d'ordine ed email e confermi. Ricevi subito via email una ricevuta con data e ora. In alternativa scrivi a <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a>, anche con il modulo tipo che trovi nella pagina <a href="/diritto-di-recesso">Diritto di recesso</a>.</p>
<h2>2. Rispedisci i prodotti</h2>
<p>Entro 14 giorni dalla comunicazione rispedisci i prodotti, con un corriere a tua scelta e a tue spese, a: <strong>Pallavolo Scandicci Savino Del Bene, {$italiaIndirizzo}</strong>, indicando il numero d'ordine. Imballali con cura: puoi provarli come faresti in negozio, ma se li restituisci indossati, lavati, rovinati o senza etichette potremo trattenere dal rimborso la diminuzione di valore.</p>
<h2>3. Ricevi il rimborso</h2>
<p>Ti rimborsiamo il prezzo e le spese di consegna standard entro 14 giorni dalla tua comunicazione, con lo stesso metodo di pagamento che hai usato e senza costi. Possiamo attendere di ricevere i prodotti, o la prova che li hai spediti, prima di effettuarlo.</p>
<h2>Prodotti esclusi</h2>
<p>Non si possono restituire per recesso i prodotti personalizzati su tua richiesta (per esempio con nome e numero a tua scelta) e i prodotti sigillati per motivi igienici aperti dopo la consegna. La scheda del prodotto lo indica prima dell'acquisto.</p>
<h2>Prodotto difettoso o diverso da quello ordinato?</h2>
<p>Non è un reso per recesso: si applica la garanzia legale di conformità di due anni. Scrivici entro due mesi da quando hai scoperto il difetto, con il numero d'ordine e una foto: ripariamo o sostituiamo il prodotto a nostre spese, oppure, se non è possibile, riduciamo il prezzo o ti rimborsiamo. I dettagli sono al punto 7 delle <a href="/condizioni-di-vendita">Condizioni di vendita</a>.</p>
<h2>Modificare o annullare un ordine</h2>
<p>Un ordine inviato non si può più modificare dal sito. Se vuoi annullarlo prima della spedizione scrivici subito a <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a>: se non è ancora in lavorazione lo annulliamo e ti rimborsiamo. Dopo la spedizione puoi sempre esercitare il recesso.</p>
HTML,
            'en' => <<<HTML
<p><strong>Changed your mind? You have 14 days from delivery to withdraw</strong> from the contract, without giving any reason. This also applies to items won in online auctions.</p>
<h2>1. Tell us you are withdrawing</h2>
<p>The easiest way is the <a href="/en/recesso">Withdraw from contract</a> function: enter your name, order number and email and confirm. You immediately receive a receipt by email with the date and time. Alternatively write to <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a>, also using the model form on the <a href="/en/diritto-di-recesso">Right of withdrawal</a> page.</p>
<h2>2. Send the products back</h2>
<p>Within 14 days of your communication, send the products back with a courier of your choice and at your own cost to: <strong>Pallavolo Scandicci Savino Del Bene, {$italiaIndirizzo}, Italy</strong>, quoting your order number. Pack them carefully: you may try them as you would in a shop, but if they come back worn, washed, damaged or without labels we may deduct the loss in value from the refund.</p>
<h2>3. Get your refund</h2>
<p>We refund the price and the standard delivery cost within 14 days of your communication, using the payment method you used and at no cost to you. We may wait until we receive the products, or proof that you have sent them, before refunding.</p>
<h2>Excluded products</h2>
<p>Products personalised at your request (for example with a name and number of your choice) and sealed products unsealed after delivery for hygiene reasons cannot be returned on withdrawal. The product page says so before purchase.</p>
<h2>Faulty product, or not what you ordered?</h2>
<p>That is not a withdrawal: the two-year legal guarantee of conformity applies. Write to us within two months of discovering the defect, with your order number and a photo: we repair or replace the product at our expense or, if that is not possible, reduce the price or refund you. Details are in section 7 of the <a href="/en/condizioni-di-vendita">Terms of sale</a>.</p>
<h2>Changing or cancelling an order</h2>
<p>An order cannot be changed on the Site once submitted. If you want to cancel it before dispatch, write to <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> straight away: if it is not yet being processed we cancel it and refund you. After dispatch you can always withdraw.</p>
HTML,
        ],
    ],

    // Il regolamento delle aste non e' una pagina ma un'impostazione
    // (`auctions.rules_text`), mostrata nella pagina di ogni asta: si
    // pubblica con la stessa regola a guardia delle pagine.
    'regolamento-aste' => [
        'titolo' => ['it' => 'Regolamento aste', 'en' => 'Auction rules'],
        'descrizione' => ['it' => '', 'en' => ''],
        'firme' => ['È possibile partecipare all’asta esclusivamente previo login'],
        'contenuto' => [
            'it' => <<<'HTML'
<p>Le aste si svolgono esclusivamente online su questo sito. Per fare offerte serve un account dello Shop e una carta di pagamento verificata tramite Stripe: la verifica non addebita nulla.</p>
<h2>Oggetti, durata e base d'asta</h2>
<p>Ogni asta riguarda l'oggetto descritto nella sua pagina, con la base d'asta, la data e l'ora di chiusura. Se non diversamente indicato, gli oggetti sono cimeli originali indossati o utilizzati, quindi usati: le condizioni sono descritte e fotografate nella pagina. Un'asta può avere un prezzo di riserva, di cui la pagina segnala la presenza e il raggiungimento: se alla chiusura l'offerta più alta non lo raggiunge, l'oggetto non viene aggiudicato.</p>
<h2>Offerte</h2>
<ul>
<li>Ogni offerta deve superare quella più alta in corso almeno dell'incremento minimo indicato nella pagina dell'asta, e non può superarla di più del salto massimo consentito, anch'esso indicato: le offerte fuori da questi limiti non vengono accettate.</li>
<li>Un'offerta valida è vincolante e non può essere ritirata.</li>
<li>Se arriva un'offerta negli ultimi minuti, la chiusura si sposta in avanti di qualche minuto, così che tutti possano rispondere.</li>
<li>Ricevi un'email quando la tua offerta viene superata. Nella pagina dell'asta gli offerenti compaiono con il nome abbreviato.</li>
</ul>
<h2>Aggiudicazione e pagamento</h2>
<p>Alla chiusura l'oggetto è aggiudicato a chi ha fatto l'offerta più alta, che riceve un'email con il collegamento per il pagamento. Il prezzo finale è l'importo dell'offerta vincente, IVA inclusa, più le spese di spedizione indicate al checkout; non ci sono commissioni. Il vincitore ha il tempo indicato nella pagina dell'asta per pagare; se non paga entro il termine, l'oggetto viene proposto al secondo miglior offerente alle condizioni della sua offerta.</p>
<h2>Spedizione, recesso e garanzia</h2>
<p>Agli oggetti aggiudicati si applicano le <a href="/condizioni-di-vendita">Condizioni di vendita</a> dello Shop: la spedizione è descritta nella pagina <a href="/spedizioni">Spedizioni</a> e, come per ogni acquisto online, il vincitore ha 14 giorni dalla consegna per recedere, anche con la funzione <a href="/recesso">Recedi dal contratto</a>, e gode della garanzia legale di conformità.</p>
<h2>Beneficenza</h2>
<p>Quando un'asta è a scopo benefico, la sua pagina indica l'ente destinatario e la quota del ricavato che gli viene devoluta.</p>
<h2>Dati personali</h2>
<p>I dati dei partecipanti sono trattati per gestire le offerte, l'aggiudicazione e la vendita, come descritto nella <a href="/privacy-policy">Privacy Policy</a>.</p>
HTML,
            'en' => <<<'HTML'
<p>Auctions take place exclusively online on this website. To bid you need a Shop account and a payment card verified through Stripe: the verification does not charge anything.</p>
<h2>Items, duration and starting price</h2>
<p>Each auction concerns the item described on its page, with the starting price and the closing date and time. Unless stated otherwise, items are original memorabilia that have been worn or used, i.e. second-hand: their condition is described and photographed on the page. An auction may have a reserve price, whose presence and achievement are shown on the page: if the highest bid does not reach it at closing, the item is not awarded.</p>
<h2>Bids</h2>
<ul>
<li>Each bid must exceed the current highest bid by at least the minimum increment shown on the auction page, and may not exceed it by more than the maximum jump allowed, also shown there: bids outside these limits are not accepted.</li>
<li>A valid bid is binding and cannot be withdrawn.</li>
<li>If a bid arrives in the last few minutes, the closing time moves forward by a few minutes so that everyone can respond.</li>
<li>You receive an email when you are outbid. On the auction page bidders are shown with a shortened name.</li>
</ul>
<h2>Award and payment</h2>
<p>At closing the item is awarded to the highest bidder, who receives an email with the payment link. The final price is the amount of the winning bid, VAT included, plus the shipping cost shown at checkout; there are no fees. The winner has the time shown on the auction page to pay; if payment is not made in time, the item is offered to the second-highest bidder on the terms of their bid.</p>
<h2>Shipping, withdrawal and guarantee</h2>
<p>Items won at auction are subject to the Shop's <a href="/en/condizioni-di-vendita">Terms of sale</a>: shipping is described on the <a href="/en/spedizioni">Shipping</a> page and, as for any online purchase, the winner has 14 days from delivery to withdraw, including through the <a href="/en/recesso">Withdraw from contract</a> function, and benefits from the legal guarantee of conformity.</p>
<h2>Charity</h2>
<p>When an auction is for charity, its page states the beneficiary and the share of the proceeds donated.</p>
<h2>Personal data</h2>
<p>Participants' data are processed to manage bids, award and sale, as described in the <a href="/en/privacy-policy">Privacy Policy</a>.</p>
HTML,
        ],
    ],
];
