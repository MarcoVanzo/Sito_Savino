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
    ],
];
