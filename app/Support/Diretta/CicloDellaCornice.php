<?php

namespace App\Support\Diretta;

use Carbon\CarbonInterface;

/**
 * Quando compare la cornice sponsor durante la gara, senza nessuno in regia.
 *
 * Il ciclo `k` comincia a `inizio + k × ogni` e dura `durata` secondi; non ne
 * comincia nessuno dopo `fine`. La creatività del ciclo `k` è la `k`-esima
 * della rotazione (modulo la sua lunghezza): così tutti gli spettatori vedono
 * lo stesso sponsor negli stessi istanti. Il conto vero lo fa il browser
 * (`resources/js/Support/cicloDellaCornice.js`, stesse regole); qui serve
 * all'anteprima del pannello e alla regia.
 */
class CicloDellaCornice
{
    /**
     * I primi istanti in cui comincia un ciclo.
     *
     * @return list<CarbonInterface>
     */
    public static function primiIstanti(CarbonInterface $inizio, CarbonInterface $fine, int $ogniMinuti, int $quanti = 3): array
    {
        $istanti = [];

        for ($k = 0; $k < $quanti && $ogniMinuti > 0; $k++) {
            $istante = $inizio->copy()->addMinutes($k * $ogniMinuti);

            if ($istante->greaterThanOrEqualTo($fine)) {
                break;
            }

            $istanti[] = $istante;
        }

        return $istanti;
    }

    /**
     * Il prossimo ciclo che comincia da `adesso` in poi (o quello in corso).
     *
     * @return array{numero: int, inizio: CarbonInterface, fine: CarbonInterface}|null
     */
    public static function prossimo(CarbonInterface $inizio, CarbonInterface $fine, int $ogniMinuti, int $durataSecondi, CarbonInterface $adesso): ?array
    {
        if ($ogniMinuti <= 0 || $adesso->greaterThanOrEqualTo($fine)) {
            return null;
        }

        $periodo = $ogniMinuti * 60;
        $trascorsi = max(0, $adesso->getTimestamp() - $inizio->getTimestamp());
        $numero = intdiv($trascorsi, $periodo);

        // Quello corrente è già finito: tocca al successivo.
        if ($adesso->greaterThanOrEqualTo($inizio) && $trascorsi - $numero * $periodo >= $durataSecondi) {
            $numero++;
        }

        $comincia = $inizio->copy()->addSeconds($numero * $periodo);

        if ($comincia->greaterThanOrEqualTo($fine)) {
            return null;
        }

        return ['numero' => $numero, 'inizio' => $comincia, 'fine' => $comincia->copy()->addSeconds($durataSecondi)];
    }
}
