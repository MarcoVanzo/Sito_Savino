<?php

return [
    'order_status' => [
        'pending' => 'In attesa',
        'processing' => 'In Lavorazione',
        'paid' => 'Pagato',
        'shipped' => 'Spedito',
        'delivered' => 'Consegnato',
        'cancelled' => 'Annullato',
        'refunded' => 'Rimborsato',
    ],

    'auction_status' => [
        'draft' => 'Bozza',
        'scheduled' => 'Programmata',
        'active' => 'Attiva',
        'ended' => 'Conclusa',
        'cancelled' => 'Annullata',
    ],

    'game_status' => [
        'scheduled' => 'Da giocare',
        'in_progress' => 'In corso',
        'completed' => 'Conclusa',
        'postponed' => 'Rinviata',
    ],

    'game' => [
        'matchday' => ':numberª Giornata',

        // Fase del campionato come la pubblica la Lega. La chiave è lo slug del
        // valore grezzo salvato in `games.phase`: una fase non ancora tradotta
        // viene mostrata così com'è arrivata.
        'phase' => [
            'andata' => 'Andata',
            'ritorno' => 'Ritorno',
        ],
    ],
];
