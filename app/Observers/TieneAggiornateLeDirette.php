<?php

namespace App\Observers;

use App\Jobs\RipubblicaLoStatoDelleDirette;
use App\Models\LiveCreative;

/**
 * Una creatività spenta, cambiata o cancellata deve sparire (o cambiare)
 * anche nelle dirette già pubblicate: il JSON letto dal sito la elencava
 * finché qualcuno non risalvava la diretta della gara.
 */
class TieneAggiornateLeDirette
{
    public function saved(LiveCreative $creativita): void
    {
        RipubblicaLoStatoDelleDirette::richiedi();
    }

    public function deleted(LiveCreative $creativita): void
    {
        RipubblicaLoStatoDelleDirette::richiedi();
    }
}
