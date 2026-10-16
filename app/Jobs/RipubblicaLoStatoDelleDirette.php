<?php

namespace App\Jobs;

use App\Observers\CacheInvalidationObserver;
use App\Services\Diretta\RegiaDellaDiretta;
use App\Support\Diretta\DirettaPerIlSito;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Riscrive il JSON di stato delle dirette vicine (gare di ieri, oggi e dei
 * prossimi giorni) dopo che una creatività è cambiata, e svuota le pagine in
 * cache che portano il pre-roll (home e calendario).
 *
 * Parte a risposta già data (`dispatchAfterResponse`): dal pannello il file
 * della creatività si salva dopo il record, e facendolo subito il JSON e le
 * pagine rimesse in cache porterebbero ancora l'indirizzo vecchio, che il
 * file nuovo ha appena cancellato.
 *
 * Una sola volta per richiesta, anche se ne cambiano dieci (cancellazione in
 * blocco, interruttori in tabella): `richiedi()`.
 */
class RipubblicaLoStatoDelleDirette
{
    use Dispatchable;

    private const IN_ATTESA = 'diretta.ripubblicazione_in_attesa';

    public static function richiedi(): void
    {
        if (app()->bound(self::IN_ATTESA)) {
            return;
        }

        app()->instance(self::IN_ATTESA, true);
        self::dispatchAfterResponse();
    }

    public function handle(RegiaDellaDiretta $regia, CacheInvalidationObserver $cache): void
    {
        // Prima di lavorare: un cambio che arriva intanto ne chiede un'altra.
        app()->forgetInstance(self::IN_ATTESA);

        $regia->ripubblicaLeDiretteDaGiocare();
        $cache->svuotaLeChiavi(DirettaPerIlSito::CHIAVI_DI_CACHE);
    }
}
