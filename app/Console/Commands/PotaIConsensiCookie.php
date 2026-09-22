<?php

namespace App\Console\Commands;

use App\Models\ConsensoCookie;
use Illuminate\Console\Command;

/**
 * Toglie dal registro i consensi troppo vecchi per servire.
 *
 * La prova del consenso si conserva finché può essere richiesta: tenerla oltre
 * è raccolta di dati senza scopo, e il registro è fatto per dimostrare di
 * rispettare il GDPR, non per violarlo di suo. Dodici mesi è anche la durata
 * massima che diamo al consenso stesso.
 */
class PotaIConsensiCookie extends Command
{
    protected $signature = 'consensi:pota {--mesi=12 : Da quanti mesi in là un consenso si cancella}';

    protected $description = 'Cancella dal registro i consensi ai cookie più vecchi di N mesi';

    public function handle(): int
    {
        $mesi = max(1, (int) $this->option('mesi'));
        $limite = now()->subMonths($mesi);

        $cancellati = ConsensoCookie::query()->where('created_at', '<', $limite)->delete();

        $this->info($cancellati === 0
            ? 'Nessun consenso da togliere.'
            : $cancellati.' '.($cancellati === 1 ? 'consenso cancellato' : 'consensi cancellati').', più vecchi del '.$limite->format('d/m/Y').'.');

        return self::SUCCESS;
    }
}
