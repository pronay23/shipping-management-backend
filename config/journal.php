<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment term → account mapping
    |--------------------------------------------------------------------------
    |
    | Maps a money receipt's payment term to the chart-of-account code that
    | receives the debit when a receipt is posted to the journal.
    |
    */

    'payment_term_accounts' => [
        'Cash' => '1010',
        'Cheque' => '1020',
        'Pay Order' => '1020',
        'Bank Transfer' => '1020',
        'TT / Wire Transfer' => '1020',
        'Online Payment' => '1020',
    ],

];
