<?php

namespace App\Console\Commands;

use App\Services\Diretta\RegiaDellaDiretta;
use Illuminate\Console\Command;

/**
 * Riporta in onda le dirette il cui spot della regia è finito.
 *
 * Il browser lo sa già da `fino_alle`; il JSON però direbbe ancora «pausa»
 * a chi apre la diretta più tardi. Una query su `live_sessions` al minuto,
 * senza nulla da fare fuori dalle gare. Riprova anche le dirette il cui
 * ultimo cambio non è arrivato sul JSON (Spaces giù).
 */
class ChiudiLePauseDellaDiretta extends Command
{
    protected $signature = 'diretta:chiudi-le-pause';

    protected $description = 'Riporta in onda le dirette con lo spot della regia scaduto';

    public function handle(RegiaDellaDiretta $regia): int
    {
        $chiuse = $regia->chiudiLePauseScadute();

        if ($chiuse > 0) {
            $this->info("Pause chiuse: {$chiuse}");
        }

        $ripubblicate = $regia->ripubblicaLeNonArrivate();

        if ($ripubblicate > 0) {
            $this->info("Dirette ripubblicate dopo un errore: {$ripubblicate}");
        }

        return self::SUCCESS;
    }
}
