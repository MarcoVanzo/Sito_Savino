<?php

/*
 * Testi delle email transazionali dello shop.
 *
 * La lingua non è quella della richiesta: le mail sono in coda (`ShouldQueue`)
 * e vengono spedite quando il contesto HTTP non esiste più. Ogni Mailable
 * dichiara la propria locale nel costruttore leggendola da `orders.locale`
 * (o `users.locale` per le aste), valorizzate al momento della creazione.
 */

return [
    'layout' => [
        'auto_generated' => 'Questa email è stata generata automaticamente. Non rispondere a questo indirizzo.',
    ],

    'common' => [
        'order_number' => 'Numero Ordine',
        'order_date' => 'Data Ordine',
        'date' => 'Data',
        'status' => 'Stato',
        'amount' => 'Importo',
        'total' => 'Totale',
        'order' => 'Ordine',
        'view_order' => 'Vedi il tuo ordine →',
        'order_details' => 'Dettagli ordine →',
        'removed_product' => 'Prodotto rimosso',
    ],

    'confirmation' => [
        'subject' => 'Conferma Ordine #:number',
        'title' => 'Conferma Ordine #:number',
        'heading' => 'Grazie per il tuo ordine!',
        'intro' => 'Abbiamo ricevuto il tuo ordine e lo stiamo elaborando.',
        'items_heading' => 'Articoli ordinati',
        'col_product' => 'Prodotto',
        'col_quantity' => 'Qtà',
        'col_price' => 'Prezzo',
        'col_total' => 'Totale',
        'subtotal' => 'Subtotale',
        'shipping' => 'Spedizione',
        'coupon_discount' => 'Sconto coupon',
        'bank_transfer_heading' => '📋 Istruzioni per il Bonifico Bancario',
        'bank_beneficiary' => 'Beneficiario:',
        'bank_iban' => 'IBAN:',
        'bank_reason' => 'Causale:',
        'bank_reason_value' => 'Ordine :number',
        'bank_deadline' => 'Ti preghiamo di effettuare il pagamento entro :days giorni lavorativi dalla data dell\'ordine. In caso contrario, l\'ordine verrà automaticamente annullato.',
        'cta_intro' => 'Puoi seguire il tuo ordine in qualsiasi momento:',
    ],

    'shipped' => [
        'subject' => 'Il tuo ordine #:number è stato spedito!',
        'title' => 'Ordine #:number spedito',
        'heading' => '🚚 Il tuo ordine è in viaggio!',
        'intro' => 'Ottima notizia! Il tuo ordine è stato spedito.',
        'tracking_number' => 'Numero Tracking',
        'shipped_at' => 'Data Spedizione',
        'track_button' => '📦 Traccia la tua spedizione',
        'delivery_estimate_label' => 'Tempi di consegna stimati:',
        'delivery_estimate' => 'la spedizione richiede generalmente 2-5 giorni lavorativi, in base alla destinazione.',
        'cta_intro' => 'Puoi consultare i dettagli del tuo ordine:',
    ],

    'status_changed' => [
        'subject' => 'Aggiornamento Ordine #:number — :status',
        'title' => 'Aggiornamento Ordine #:number',
        'processing_heading' => 'Il tuo ordine è in lavorazione',
        'processing_intro' => 'Abbiamo preso in carico il tuo ordine e lo stiamo preparando per la spedizione.',
        'delivered_heading' => 'Ordine consegnato! ✅',
        'delivered_intro' => 'Il tuo ordine è stato consegnato con successo. Speriamo che tu sia soddisfatto del tuo acquisto!',
        'cancelled_heading' => 'Ordine annullato',
        'cancelled_intro' => 'Il tuo ordine è stato annullato. Se hai effettuato un pagamento, verrai contattato per il rimborso.',
        'refunded_heading' => 'Rimborso elaborato',
        'refunded_intro' => 'Il rimborso per il tuo ordine è stato elaborato. L\'importo verrà accreditato sul metodo di pagamento originale.',
        'default_heading' => 'Aggiornamento sul tuo ordine',
        'default_intro' => 'Lo stato del tuo ordine è stato aggiornato.',
        'cta_intro' => 'Puoi visualizzare i dettagli del tuo ordine in qualsiasi momento:',
        'contact_note' => 'Per qualsiasi domanda sul tuo ordine, non esitare a contattarci rispondendo a questa email o scrivendo a',
    ],

    'cancelled' => [
        'subject' => 'Ordine #:number annullato per mancato pagamento',
        'title' => 'Ordine #:number annullato',
        'heading' => 'Ordine annullato',
        'intro' => 'Ci dispiace informarti che il tuo ordine <strong>#:number</strong> del :date è stato <strong>automaticamente annullato</strong> per mancato pagamento entro i termini previsti.',
        'status_value' => 'Annullato',
        'retry' => 'Se desideri ancora acquistare i prodotti, puoi effettuare un nuovo ordine visitando il nostro shop.',
        'shop_button' => 'Visita lo Shop',
        'contact' => 'Per qualsiasi domanda, contattaci a :email.',
    ],

    'payment_reminder' => [
        'subject' => 'Promemoria pagamento ordine #:number',
        'heading' => 'Promemoria: pagamento in attesa',
        'intro' => 'Il tuo ordine <strong>#:number</strong> del :date è ancora in attesa di pagamento.',
        'deadline' => 'Ti ricordiamo di effettuare il bonifico entro <strong>:days giorni</strong> dalla data dell\'ordine, altrimenti verrà automaticamente annullato.',
        'iban' => 'IBAN',
        'beneficiary' => 'Intestatario',
        'reason' => 'Causale',
        'reason_value' => 'Ordine #:number',
        'cta' => 'Vedi il tuo ordine',
    ],

    'refund' => [
        'subject' => 'Rimborso Ordine #:number confermato',
        'title' => 'Rimborso Ordine #:number',
        'heading' => 'Rimborso confermato',
        'intro' => 'Il rimborso per il tuo ordine è stato elaborato.',
        'refunded_amount' => 'Importo rimborsato',
        'timing_label' => 'Tempistiche:',
        'timing' => 'il rimborso verrà accreditato sul metodo di pagamento originale entro <strong>5-10 giorni lavorativi</strong>. I tempi possono variare in base al tuo istituto bancario.',
        'questions' => 'Se hai domande sul rimborso, non esitare a contattarci.',
    ],

    'auction_won' => [
        'subject' => 'Hai vinto l\'asta: :title!',
        'title' => 'Hai vinto l\'asta: :title',
        'heading' => '🎉 Congratulazioni, hai vinto l\'asta!',
        'intro' => 'La tua offerta è risultata la vincente. Completa il pagamento per assicurarti il prodotto.',
        'auction' => 'Asta',
        'winning_amount' => 'Importo vincente',
        'payment_deadline' => 'Scadenza pagamento',
        'cta' => 'Completa il pagamento',
        'warning_label' => '⚠️ Attenzione:',
        'warning' => 'Se non effettui il pagamento entro la scadenza indicata, l\'asta verrà assegnata al prossimo offerente.',
    ],

    'auction_outbid' => [
        'subject' => 'Sei stato superato nell\'asta: :title',
        'title' => 'Sei stato superato nell\'asta: :title',
        'heading' => 'La tua offerta è stata superata!',
        'intro' => 'Un altro utente ha fatto un\'offerta più alta sull\'asta a cui stai partecipando.',
        'auction' => 'Asta',
        'highest_bid' => 'Offerta attuale più alta',
        'time_left' => 'Tempo rimanente',
        'ended' => 'Asta terminata',
        'cta' => 'Rilancia la tua offerta',
        'footer' => 'Non perdere l\'occasione! Accedi al sito e fai una nuova offerta prima che l\'asta termini.',
    ],
];
