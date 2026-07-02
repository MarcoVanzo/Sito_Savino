<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Illuminate\Console\Command;

class AnalyzeGalleryCommand extends Command
{
    protected $signature = 'gallery:analyze
        {--event= : ID di un evento specifico da analizzare}
        {--unreviewed : Analizza solo le foto marcate "da revisionare"}
        {--all : Analizza tutte le foto (ignora lo stato precedente)}
        {--force : Non chiedere conferma}';

    protected $description = 'Invia le foto della gallery per l\'analisi AI in background (riconoscimento facciale + SEO)';

    public function handle(): int
    {
        $query = GalleryImage::query()->whereHas('media');

        // Filtro per evento
        if ($eventId = $this->option('event')) {
            $event = GalleryEvent::find($eventId);
            if (! $event) {
                $this->error("Evento con ID {$eventId} non trovato.");

                return self::FAILURE;
            }
            $query->where('gallery_event_id', $eventId);
            $this->info("Filtro: evento \"{$event->title}\" (ID: {$eventId})");
        }

        // Filtro solo da revisionare
        if ($this->option('unreviewed')) {
            $query->where('needs_review', true);
            $this->info('Filtro: solo foto da revisionare');
        }

        // Se nessun filtro specifico e non --all, chiedi conferma
        if (! $this->option('event') && ! $this->option('unreviewed') && ! $this->option('all')) {
            $this->error('Specifica almeno un filtro: --event=ID, --unreviewed, oppure --all per tutte.');

            return self::FAILURE;
        }

        $count = $query->count();

        if ($count === 0) {
            $this->warn('Nessuna foto trovata con i filtri specificati.');

            return self::SUCCESS;
        }

        $this->info("Foto da analizzare: {$count}");

        // Conferma (a meno di --force)
        if (! $this->option('force') && ! $this->confirm("Vuoi procedere con l'analisi di {$count} foto?")) {
            $this->info('Operazione annullata.');

            return self::SUCCESS;
        }

        // Dispatch dei job
        $dispatched = 0;
        $query->chunkById(100, function ($images) use (&$dispatched) {
            foreach ($images as $image) {
                AnalyzeGalleryImageJob::dispatch($image);
                $dispatched++;
            }
        });

        $this->info("✅ {$dispatched} job di analisi accodati con successo.");
        $this->info('I risultati appariranno nel pannello admin man mano che i job vengono elaborati.');

        return self::SUCCESS;
    }
}
