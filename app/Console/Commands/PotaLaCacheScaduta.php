<?php

namespace App\Console\Commands;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Toglie dalla tabella della cache le righe scadute.
 *
 * Il driver `database` cancella una riga scaduta solo quando qualcuno la
 * rilegge: quelle che nessuno chiede più restano. Sono le pagine intere di una
 * generazione già superata (`GenerazioneDiCache`), i contatori dei limiti di
 * richiesta di indirizzi visti una volta sola. Il 6/10/2026 in produzione
 * erano 5 800 righe scadute su 6 500, 30 MB di HTML; l'unica pulizia era il
 * `cache:clear` di ogni avvio.
 *
 * Con un altro driver (Redis in locale, array nei test) le scadenze le gestisce
 * il driver e il comando non fa niente.
 */
class PotaLaCacheScaduta extends Command
{
    protected $signature = 'cache:pota-scadute';

    protected $description = 'Cancella le righe scadute della cache su database';

    /** Righe cancellate per volta: una DELETE enorme blocca la tabella che ogni visita legge. */
    private const BLOCCO = 1000;

    public function handle(): int
    {
        $nome = (string) config('cache.default');
        $store = Cache::store($nome)->getStore();

        if (! $store instanceof DatabaseStore) {
            $this->info('La cache non è su database: niente da potare.');

            return self::SUCCESS;
        }

        $tabella = $store->getConnection()->table((string) config("cache.stores.{$nome}.table", 'cache'));
        $adesso = now()->getTimestamp();
        $cancellate = 0;

        do {
            $blocco = (clone $tabella)->where('expiration', '<=', $adesso)->limit(self::BLOCCO)->delete();
            $cancellate += $blocco;
        } while ($blocco === self::BLOCCO);

        $this->info($cancellate === 0 ? 'Nessuna riga scaduta.' : "Righe scadute tolte: {$cancellate}.");

        return self::SUCCESS;
    }
}
