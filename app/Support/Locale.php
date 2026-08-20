<?php

namespace App\Support;

/**
 * Lingua corrente, ristretta a quelle che il sito supporta davvero.
 *
 * Serve dove la locale viene *persistita* (ordini, utenti, iscritti) per essere
 * riletta più tardi da un worker: una locale inattesa finita in colonna manda
 * le traduzioni in fallback silenzioso mesi dopo, senza errori visibili.
 */
class Locale
{
    public static function current(): string
    {
        $supported = config('app.supported_locales', ['it', 'en']);
        $locale = app()->getLocale();

        return in_array($locale, $supported, true) ? $locale : 'it';
    }
}
