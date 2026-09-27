<?php

namespace App\Services\Lvf;

use Illuminate\Support\Str;

/**
 * Traduce la fase di una gara ("Andata", "Ritorno") per la lingua corrente.
 *
 * `games.phase` conserva la stringa grezza della Lega, che è italiana: è il
 * dato di origine e non va tradotto in archivio, altrimenti la prossima
 * sincronizzazione lo riscriverebbe comunque in italiano e resterebbe un valore
 * diverso a seconda di quando è stato importato. La traduzione appartiene alla
 * presentazione, come già avviene per la giornata (`enums.game.matchday`).
 */
final class LvfPhaseLabel
{
    /**
     * @return string|null null quando non c'è una fase da mostrare
     */
    public static function translate(?string $phase): ?string
    {
        $phase = trim((string) $phase);

        if ($phase === '') {
            return null;
        }

        // Le fasi della CEV sono composte: turno e giornata ("Play Off · Home
        // Matches"). Si traduce ogni parte, così le combinazioni non vanno
        // elencate una per una.
        if (str_contains($phase, ' · ')) {
            return implode(' · ', array_map(
                fn (string $parte): string => self::translate($parte) ?? $parte,
                explode(' · ', $phase),
            ));
        }

        $key = 'enums.game.phase.'.Str::slug($phase, '_');
        $label = __($key);

        // `__()` restituisce la chiave stessa quando la traduzione manca. Una
        // fase che oggi non conosciamo (la Lega può introdurre "Play Off" o
        // "Poule Salvezza") deve continuare a essere mostrata com'è, non
        // sostituita da "enums.game.phase.play_off".
        return is_string($label) && $label !== $key ? $label : $phase;
    }
}
