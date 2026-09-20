<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live market betting (Satta King + Kalyan Matka)
    |--------------------------------------------------------------------------
    | Payout rule: 1₹ bet → 9₹ win (stake × prize_multiplier).
    */

    'ticket_price' => (float) env('BET_TICKET_PRICE', 1),

    'prize_multiplier' => (float) env('BET_PRIZE_MULTIPLIER', 9),

    'pick_count' => 1,

    'min_number' => 0,

    'max_number' => 99,

    'min_amount' => (float) env('BET_MIN_AMOUNT', 1),

    'max_amount' => (float) env('BET_MAX_AMOUNT', 100000),

];
