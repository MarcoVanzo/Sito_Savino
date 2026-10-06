<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Una famiglia di chiavi di cache che si butta tutta insieme con una sola
 * scrittura: la generazione entra nel nome delle chiavi, e cambiarla le rende
 * irraggiungibili. Le copie vecchie scadono da sole e `cache:pota-scadute`
 * toglie le righe dal database.
 *
 * Prima ogni famiglia si buttava chiave per chiave: le pagine intere tenevano
 * un registro degli indirizzi in cache e le varianti della gallery si
 * cercavano atleta per atleta. Con la cache sul database ogni chiave era una
 * query: salvare una rosa dal pannello ne faceva centinaia (Sentry
 * SITO-SAVINO-Z, ottobre 2026), e il registro, riscritto a ogni pagina nuova,
 * perdeva voci quando due visite si sovrapponevano.
 */
final class GenerazioneDiCache
{
    private const PREFISSO = 'generazione:';

    public static function attuale(string $famiglia): string
    {
        return (string) Cache::get(self::PREFISSO.$famiglia, '0');
    }

    public static function rinnova(string $famiglia): void
    {
        Cache::forever(self::PREFISSO.$famiglia, Str::random(12));
    }
}
