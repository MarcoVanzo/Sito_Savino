<?php

namespace App\Console\Commands;

use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Console\Command;

class RetryFailedNewsletterSync extends Command
{
    protected $signature = 'newsletter:retry-sync {--limit=50 : Numero massimo di contatti da risincronizzare}';

    protected $description = 'Ritenta la sincronizzazione con ActiveCampaign per gli iscritti non ancora sincronizzati';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $subscribers = NewsletterSubscriber::unsynced()
            ->active()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($subscribers->isEmpty()) {
            $this->info('Nessun iscritto da sincronizzare.');

            return self::SUCCESS;
        }

        $this->info("Risincronizzazione di {$subscribers->count()} iscritti...");

        foreach ($subscribers as $subscriber) {
            SyncNewsletterToActiveCampaign::dispatch($subscriber);
            $this->line("  → Dispatched: {$subscriber->email}");
        }

        $this->info('Job di sincronizzazione accodati con successo.');

        return self::SUCCESS;
    }
}
