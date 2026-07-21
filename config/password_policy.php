<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Policy password
    |--------------------------------------------------------------------------
    |
    | Valori centralizzati della policy. Tenerli qui evita che le stesse costanti
    | vengano ripetute (e divergano) fra middleware, regole di validazione,
    | banner del pannello e notifiche.
    |
    */

    // Ogni quanti mesi la password scade e va cambiata.
    'expires_after_months' => (int) env('PASSWORD_EXPIRES_AFTER_MONTHS', 6),

    // Quanti giorni prima della scadenza si inizia ad avvisare l'utente.
    'warn_before_days' => (int) env('PASSWORD_WARN_BEFORE_DAYS', 10),

    // Quante password precedenti vengono ricordate e non possono essere riusate.
    // Include quella attualmente in uso.
    'history_size' => (int) env('PASSWORD_HISTORY_SIZE', 6),

];
