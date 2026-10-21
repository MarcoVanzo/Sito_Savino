<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Toglie dagli ordini `_fbp`, `_fbc` e lo user agent salvati per la
 * Conversions API, quando non servono piu'.
 *
 * L'invio li cancella da se' (InviaAcquistoAMeta); restano gli ordini il cui
 * acquisto non parte mai: un bonifico, un pagamento non arrivato, la
 * Conversions API spenta nel frattempo. Meta accetta un evento fino a 7 giorni
 * dopo: oltre, tenerli non ha scopo.
 */
class DimenticaGliIdentificativiDiMeta extends Command
{
    public const GIORNI = 7;

    protected $signature = 'meta:dimentica-gli-identificativi';

    protected $description = 'Cancella dagli ordini piu\' vecchi di 7 giorni gli identificativi di Meta non piu\' utili';

    public function handle(): int
    {
        $tolti = Order::query()
            ->where('created_at', '<', now()->subDays(self::GIORNI))
            ->where(fn ($q) => $q->whereNotNull('meta_fbp')
                ->orWhereNotNull('meta_fbc')
                ->orWhereNotNull('meta_user_agent'))
            ->update(['meta_fbp' => null, 'meta_fbc' => null, 'meta_user_agent' => null]);

        $this->info($tolti === 0 ? 'Nessun ordine da ripulire.' : "Identificativi di Meta tolti da {$tolti} ordini.");

        return self::SUCCESS;
    }
}
