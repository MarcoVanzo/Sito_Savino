<?php

namespace App\Support;

/**
 * Riporta `content_data` alla forma che i template si aspettano.
 *
 * Lo stato grezzo di un modulo Filament non è quello che va in archivio: un
 * Repeater tiene le voci in una mappa `{uuid: voce}` e un FileUpload singolo
 * tiene il percorso in una mappa `{uuid: percorso}`. Quella forma è finita in
 * tabella per le pagine salvate dal pannello (piani abbonamento, progetti,
 * valori, cartelle stampa, documenti), e i componenti Vue — che chiedono un
 * elenco con `Array.isArray` — nascondevano la sezione intera.
 *
 * Qui una mappa con sole chiavi UUID torna a essere un elenco, e una mappa
 * UUID con un solo percorso torna a essere il percorso. Il resto passa
 * intatto. È idempotente: su dati già corretti non cambia nulla.
 */
class ContentData
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function normalizza(mixed $valore): mixed
    {
        if (! is_array($valore)) {
            return $valore;
        }

        if ($valore !== [] && self::haSoloChiaviUuid($valore)) {
            $voci = array_values(array_map(self::normalizza(...), $valore));

            // Un FileUpload singolo: la mappa contiene il solo percorso.
            if (count($voci) === 1 && is_string($voci[0])) {
                return $voci[0];
            }

            return $voci;
        }

        return array_map(self::normalizza(...), $valore);
    }

    /**
     * @param  array<int|string, mixed>  $valore
     */
    private static function haSoloChiaviUuid(array $valore): bool
    {
        foreach (array_keys($valore) as $chiave) {
            if (! is_string($chiave) || preg_match(self::UUID, $chiave) !== 1) {
                return false;
            }
        }

        return true;
    }
}
