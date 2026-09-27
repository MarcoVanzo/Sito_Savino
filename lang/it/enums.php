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
            // CEV Champions League (cev:sync): gironi, turni e giornate dei
            // turni a eliminazione, come li scrive il portale della CEV.
            'fase_a_gironi' => 'Fase a gironi',
            'pool_a' => 'Girone A',
            'pool_b' => 'Girone B',
            'pool_c' => 'Girone C',
            'pool_d' => 'Girone D',
            'pool_e' => 'Girone E',
            'pool_f' => 'Girone F',
            '2nd_round' => '2° turno',
            '3rd_round' => '3° turno',
            'play_off' => 'Playoff',
            'quarter_finals' => 'Quarti di finale',
            'final_four' => 'Final Four',
            'home_matches' => 'Andata',
            'away_matches' => 'Ritorno',
            'semi_finals' => 'Semifinali',
            'bronze_medal_match' => 'Finale 3° posto',
            'gold_medal_match' => 'Finale',
        ],
    ],
];
