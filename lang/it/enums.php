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
    ],
];
