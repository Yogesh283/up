<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live market betting (Satta King + Kalyan Matka)
    |--------------------------------------------------------------------------
    */

    'ticket_price' => (float) env('BET_TICKET_PRICE', 10),

    'prize_multiplier' => (float) env('BET_PRIZE_MULTIPLIER', 90),

    'pick_count' => 1,

    'min_number' => 0,

    'max_number' => 99,

    'min_amount' => (float) env('BET_MIN_AMOUNT', 10),

    'max_amount' => (float) env('BET_MAX_AMOUNT', 10000),

];
