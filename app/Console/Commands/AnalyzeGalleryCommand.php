<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyzeGalleryCommand extends Command
{
    protected $signature = 'gallery:analyze
        {--event= : ID di un evento specifico da analizzare}
        {--unreviewed : Analizza solo le foto marcate "da revisionare"}
        {--all : Analizza tutte le foto (ignora lo stato precedente)}
        {--pending : Solo le foto mai analizzate (ai_analyzed_at nullo)}
        {--limit= : Numero massimo di foto da accodare, al netto di quelle già in coda}
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

        // Solo le foto mai passate dall'AI: è il filtro del giro orario dello
        // scheduler, che recupera quelle entrate in archivio senza passare
        // dall'upload del pannello (l'import storico ne ha portate undicimila).
        if ($this->option('pending')) {
            $query->whereNull('ai_analyzed_at');
            $this->info('Filtro: solo foto mai analizzate');
        }

        // Se nessun filtro specifico e non --all, chiedi conferma
        if (! $this->option('event') && ! $this->option('unreviewed') && ! $this->option('pending') && ! $this->option('all')) {
            $this->error('Specifica almeno un filtro: --event=ID, --unreviewed, --pending, oppure --all per tutte.');

            return self::FAILURE;
        }

        // Il limite si misura sulla coda, non sul comando: se il worker è in
        // ritardo, il giro successivo non deve rimettere in coda le stesse foto.
        $limite = $this->limiteDisponibile();

        if ($limite === 0) {
            $this->info('La coda "ai" è già piena fino al limite: nessuna foto accodata.');

            return self::SUCCESS;
        }

        $count = $query->count();

        if ($limite !== null) {
            $count = min($count, $limite);
            $query->orderBy('id')->limit($limite);
        }

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

        if ($limite !== null) {
            foreach ($query->get() as $image) {
                AnalyzeGalleryImageJob::dispatch($image);
                $dispatched++;
            }
        } else {
            $query->chunkById(100, function ($images) use (&$dispatched) {
                foreach ($images as $image) {
                    AnalyzeGalleryImageJob::dispatch($image);
                    $dispatched++;
                }
            });
        }

        $this->info("✅ {$dispatched} job di analisi accodati con successo.");
        $this->info('I risultati appariranno nel pannello admin man mano che i job vengono elaborati.');

        return self::SUCCESS;
    }

    /**
     * Quante foto si possono ancora accodare rispettando `--limit`, tolte
     * quelle che aspettano già nella coda "ai". Null senza limite.
     *
     * Il conteggio legge la tabella `jobs`, cioè il driver `database` usato in
     * produzione: con Redis (locale) i job in attesa non si vedono e il limite
     * vale per il singolo lancio.
     */
    private function limiteDisponibile(): ?int
    {
        $limite = $this->option('limit');

        if ($limite === null || $limite === '') {
            return null;
        }

        $inCoda = Schema::hasTable('jobs') ? DB::table('jobs')->where('queue', 'ai')->count() : 0;

        return max(0, (int) $limite - $inCoda);
    }
}
