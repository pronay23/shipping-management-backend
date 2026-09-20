<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Period lock date (soft close)
    |--------------------------------------------------------------------------
    |
    | The first closed accounting date (YYYY-MM-DD). Journal entries dated on
    | or before this day can no longer be approved or voided; drafts may still
    | be created and edited. Leave empty to keep every period open.
    |
    */

    'period_lock_date' => env('ACCOUNTING_PERIOD_LOCK_DATE'),

];
