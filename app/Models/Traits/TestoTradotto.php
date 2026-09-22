<?php

namespace App\Models\Traits;

/**
 * Legge un campo translatable di spatie sopravvivendo alle righe storiche.
 *
 * `getTranslation()` da solo non basta: le colonne translatable contengono un
 * JSON `{"it":"…","en":"…"}`, ma i contenuti importati da WordPress hanno righe
 * in testo semplice, e spatie in quel caso restituisce una stringa vuota — un
 * titolo che sparisce, non un errore che si nota. È lo stesso inciampo che ha
 * richiesto il driver di ricerca del CMS (CLAUDE.md §9).
 */
trait TestoTradotto
{
    public function testoTradotto(string $campo, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $grezzo = $this->getAttributes()[$campo] ?? '';

        if (! is_string($grezzo) || $grezzo === '') {
            return '';
        }

        $traduzioni = json_decode($grezzo, true);

        // Non si passa da `getTranslations()`: quello scarta i valori vuoti,
        // quindi un campo tradotto ma non compilato (`{"it":""}`) tornerebbe
        // come array vuoto e qui sembrerebbe una riga in testo semplice — il
        // JSON finirebbe pubblicato così com'è.
        if (! is_array($traduzioni)) {
            return $grezzo;
        }

        $testo = $traduzioni[$locale] ?? null;

        if (is_string($testo) && $testo !== '') {
            return $testo;
        }

        // Ripiego sulla prima lingua compilata, come fa il resto del sito.
        $primaCompilata = collect($traduzioni)
            ->first(fn ($valore) => is_string($valore) && $valore !== '');

        return is_string($primaCompilata) ? $primaCompilata : '';
    }
}
