<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Social\InstagramDailySync;
use App\Services\Social\MetaException;
use Illuminate\Console\Command;

/**
 * Riempie la serie giornaliera degli insight Instagram.
 *
 * Esiste perché la Graph API non dà lo storico giorno per giorno: ogni giorno è
 * una chiamata. Aprendo la pagina se ne riempiono pochi per non far aspettare
 * nessuno; il resto lo fa questo comando di notte, quando può permettersi un
 * budget largo e la lentezza non dà fastidio a nessuno.
 */
class SyncMetaInsights extends Command
{
    protected $signature = 'social:sync-meta
                            {--days=90 : Giorni di storico da coprire}
                            {--account= : Limita a un singolo account (id)}';

    protected $description = 'Scarica da Meta gli insight Instagram giorno per giorno';

    public function handle(InstagramDailySync $sync): int
    {
        $days = max(1, (int) $this->option('days'));

        $accounts = SocialAccount::query()
            ->when($this->option('account'), fn ($query) => $query->whereKey($this->option('account')))
            ->ordered()
            ->get()
            ->filter(fn (SocialAccount $account): bool => $account->isConnected() && $account->hasInstagram());

        if ($accounts->isEmpty()) {
            $this->info('Nessun account Meta collegato con Instagram: niente da sincronizzare.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($accounts as $account) {
            try {
                $result = $sync->fill($account, $days, InstagramDailySync::MAX_CALLS_JOB);

                $this->info(sprintf(
                    '%s: %d giorni scaricati, %d ancora da recuperare.',
                    $account->name,
                    $result['filled'],
                    $result['pending'],
                ));
            } catch (MetaException $e) {
                // Un account rotto non deve impedire la sincronizzazione degli altri.
                $failed = true;
                $this->error(sprintf('%s: %s', $account->name, $e->userMessage()));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
