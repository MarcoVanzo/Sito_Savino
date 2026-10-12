<?php

namespace App\Jobs;

use App\Services\Diretta\StatoPubblicoDellaDiretta;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Riscrive il JSON di stato delle dirette vicine (gare di ieri, oggi e dei
 * prossimi giorni) dopo che una creatività è cambiata.
 *
 * Parte a risposta già data (`dispatchAfterResponse`): dal pannello il file
 * della creatività si salva dopo il record, e scrivendo subito il JSON
 * porterebbe ancora l'indirizzo vecchio.
 */
class RipubblicaLoStatoDelleDirette
{
    use Dispatchable;

    public function handle(StatoPubblicoDellaDiretta $statoPubblico): void
    {
        $statoPubblico->ripubblicaLeDiretteVicine();
    }
}
