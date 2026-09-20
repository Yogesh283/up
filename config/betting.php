<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live market betting
    |--------------------------------------------------------------------------
    */

    'min_amount' => (float) env('BET_MIN_AMOUNT', 1),

    'max_amount' => (float) env('BET_MAX_AMOUNT', 100000),

    /*
    | Satta King: pick any 00–99, payout 1₹ → 9₹
    */
    'king' => [
        'multiplier' => (float) env('BET_KING_MULTIPLIER', 9),
        'min_number' => 0,
        'max_number' => 99,
        'pick_count' => 1,
        'label' => '1₹ = 9₹',
    ],

    /*
    | Kalyan Matka style:
    | - Single Open/Close (0–9) → ank
    | - Jodi (00–99)
    | - Open/Close Pana (000–999)
    */
    'matka' => [
        'types' => [
            'single_open' => [
                'label' => 'Single Open',
                'hint' => '0–9 · Open ank',
                'multiplier' => (float) env('BET_MATKA_SINGLE_MULT', 9),
                'digits' => 1,
                'min' => 0,
                'max' => 9,
                'session' => 'open',
            ],
            'single_close' => [
                'label' => 'Single Close',
                'hint' => '0–9 · Close ank',
                'multiplier' => (float) env('BET_MATKA_SINGLE_MULT', 9),
                'digits' => 1,
                'min' => 0,
                'max' => 9,
                'session' => 'close',
            ],
            'jodi' => [
                'label' => 'Jodi',
                'hint' => '00–99',
                'multiplier' => (float) env('BET_MATKA_JODI_MULT', 90),
                'digits' => 2,
                'min' => 0,
                'max' => 99,
                'session' => 'jodi',
            ],
            'pana_open' => [
                'label' => 'Open Pana',
                'hint' => '3 digit open',
                'multiplier' => (float) env('BET_MATKA_PANA_MULT', 140),
                'digits' => 3,
                'min' => 0,
                'max' => 999,
                'session' => 'open',
            ],
            'pana_close' => [
                'label' => 'Close Pana',
                'hint' => '3 digit close',
                'multiplier' => (float) env('BET_MATKA_PANA_MULT', 140),
                'digits' => 3,
                'min' => 0,
                'max' => 999,
                'session' => 'close',
            ],
        ],
    ],

    // Legacy aliases used by older code paths
    'ticket_price' => (float) env('BET_TICKET_PRICE', 1),
    'prize_multiplier' => (float) env('BET_PRIZE_MULTIPLIER', env('BET_KING_MULTIPLIER', 9)),
    'pick_count' => 1,
    'min_number' => 0,
    'max_number' => 99,
];
