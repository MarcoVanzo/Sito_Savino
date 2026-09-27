<?php

return [
    'order_status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'paid' => 'Paid',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ],

    'auction_status' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'active' => 'Active',
        'ended' => 'Ended',
        'cancelled' => 'Cancelled',
    ],

    'game_status' => [
        'scheduled' => 'Upcoming',
        'in_progress' => 'Live',
        'completed' => 'Final',
        'postponed' => 'Postponed',
    ],

    'game' => [
        'matchday' => 'Round :number',

        // Key is the slug of the raw value stored in `games.phase`: an
        // untranslated phase falls back to the original Italian label.
        'phase' => [
            'andata' => 'First leg',
            'ritorno' => 'Second leg',
            // CEV Champions League (cev:sync): gironi, turni e giornate dei
            // turni a eliminazione, come li scrive il portale della CEV.
            'fase_a_gironi' => 'Pool phase',
            'pool_a' => 'Pool A',
            'pool_b' => 'Pool B',
            'pool_c' => 'Pool C',
            'pool_d' => 'Pool D',
            'pool_e' => 'Pool E',
            'pool_f' => 'Pool F',
            '2nd_round' => '2nd Round',
            '3rd_round' => '3rd Round',
            'play_off' => 'Play Off',
            'quarter_finals' => 'Quarter Finals',
            'final_four' => 'Final Four',
            'home_matches' => 'First leg',
            'away_matches' => 'Second leg',
            'semi_finals' => 'Semi Finals',
            'bronze_medal_match' => 'Bronze Medal Match',
            'gold_medal_match' => 'Final',
        ],
    ],
];
