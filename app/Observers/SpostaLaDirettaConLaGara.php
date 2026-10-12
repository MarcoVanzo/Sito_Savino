<?php

namespace App\Observers;

use App\Models\Game;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Carbon;

/**
 * Quando una gara cambia orario (sync della Lega o della CEV, rinvio a mano)
 * il ciclo della cornice sponsor la segue: senza, la cornice compariva agli
 * orari vecchi, prima della diretta vera.
 */
class SpostaLaDirettaConLaGara implements ShouldHandleEventsAfterCommit
{
    public function updated(Game $game): void
    {
        // Dopo il commit l'originale è già quello nuovo: il vecchio orario sta
        // in `getPrevious()`.
        $prima = $game->getPrevious()['match_date'] ?? null;

        if (! $game->wasChanged('match_date') || $prima === null) {
            return;
        }

        $game->liveSession()->first()?->seguiLaGara(Carbon::parse($prima), $game->match_date);
    }
}
