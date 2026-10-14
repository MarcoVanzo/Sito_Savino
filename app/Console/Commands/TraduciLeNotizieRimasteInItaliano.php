<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Jobs\TraduciLaNotiziaInInglese;
use App\Models\Post;
use App\Services\DeepL\DeepLClient;
use Illuminate\Console\Command;

/**
 * Rimette in coda le notizie pubblicate di recente rimaste senza inglese:
 * quota DeepL finita, DeepL irraggiungibile oltre i tentativi del job, chiave
 * aggiunta dopo la pubblicazione. Gira ogni notte; il job non chiama DeepL
 * per le notizie già a posto.
 *
 * Solo le ultime `--giorni`: l'archivio (quasi mille notizie) esaurirebbe la
 * quota gratuita del mese in un colpo.
 */
class TraduciLeNotizieRimasteInItaliano extends Command
{
    protected $signature = 'notizie:traduci-in-inglese
        {--giorni=30 : Notizie pubblicate negli ultimi N giorni}
        {--dry-run : Elenca le notizie senza accodarle}';

    protected $description = 'Accoda la traduzione DeepL delle notizie recenti rimaste senza inglese';

    public function handle(): int
    {
        if (! DeepLClient::fromConfig()->configurato()) {
            $this->warn('DEEPL_API_KEY non impostata: nessuna traduzione.');

            return self::SUCCESS;
        }

        $giorni = max(1, (int) $this->option('giorni'));
        $accodate = 0;

        Post::query()
            ->where('status', PostStatus::Published)
            ->where('published_at', '>=', now()->subDays($giorni))
            ->orderBy('id')
            ->each(function (Post $post) use (&$accodate): void {
                $campi = TraduciLaNotiziaInInglese::campiDaTradurre($post);

                if ($campi === []) {
                    return;
                }

                $this->line(sprintf('#%d %s (%s)', $post->id, $post->getTranslation('title', 'it', false), implode(', ', array_keys($campi))));

                if (! $this->option('dry-run')) {
                    TraduciLaNotiziaInInglese::dispatch($post->id);
                }

                $accodate++;
            });

        $this->info($this->option('dry-run')
            ? "Da tradurre: {$accodate} notizie (dry-run, nulla accodato)."
            : "Accodate: {$accodate} notizie.");

        return self::SUCCESS;
    }
}
