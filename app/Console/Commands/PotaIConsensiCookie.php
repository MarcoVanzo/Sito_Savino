<?php

namespace App\Console\Commands;

use App\Services\CatenaDeiConsensi;
use Illuminate\Console\Command;

/**
 * Toglie dal registro i consensi troppo vecchi per servire.
 *
 * La prova del consenso si conserva finché può essere richiesta: tenerla oltre
 * è raccolta di dati senza scopo. Ventiquattro mesi: i dodici in cui il
 * consenso vale (consenso.js, `DURATA_GIORNI`) più dodici di margine, perché
 * una contestazione su un consenso scaduto ieri deve ancora trovare la prova.
 *
 * Toglie sempre la testa del registro e lascia in `consensi_cookie_potature`
 * l'impronta dell'ultima riga tolta: è l'ancora da cui `consensi:verifica`
 * riparte (CatenaDeiConsensi::pota).
 */
class PotaIConsensiCookie extends Command
{
    public const MESI = 24;

    protected $signature = 'consensi:pota {--mesi='.self::MESI.' : Da quanti mesi in là un consenso si cancella}';

    protected $description = 'Cancella dal registro i consensi ai cookie più vecchi di N mesi';

    public function handle(): int
    {
        $mesi = max(1, (int) $this->option('mesi'));
        $limite = now()->subMonths($mesi);

        $cancellati = CatenaDeiConsensi::pota($limite);

        if ($cancellati === 0) {
            $this->info('Nessun consenso da togliere.');

            return self::SUCCESS;
        }

        $quanti = $cancellati === 1 ? 'consenso cancellato' : 'consensi cancellati';

        $this->info($cancellati.' '.$quanti.', più vecchi del '.$limite->format('d/m/Y').'.');

        return self::SUCCESS;
    }
}
