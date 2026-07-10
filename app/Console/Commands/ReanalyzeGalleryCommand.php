<?php

namespace App\Console\Commands;

use App\Models\GalleryImage;
use App\Jobs\AnalyzeGalleryImageJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;

class ReanalyzeGalleryCommand extends Command
{
    protected $signature = 'gallery:reanalyze {--chunk=100} {--force}';
    protected $description = 'Rianalizza tutte le foto della galleria tramite AI azzerando le associazioni precedenti';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Vuoi davvero rianalizzare tutte le foto della galleria? Questa operazione cancellerà tutte le associazioni facciali attuali.')) {
            return;
        }

        $this->info('1. Pulizia tabella gallery_image_person...');
        DB::table('gallery_image_person')->truncate();

        $this->info('2. Reset flag AI sulle immagini...');
        GalleryImage::query()->update([
            'ai_analyzed_at' => null,
            'needs_review' => false,
        ]);

        $this->info('3. Creazione jobs per l\'analisi...');
        
        $images = GalleryImage::has('media')->get();
        $total = $images->count();
        $this->info("Trovate {$total} foto da analizzare.");

        if ($total === 0) {
            $this->info('Nessuna foto da analizzare.');
            return;
        }

        $jobs = [];
        foreach ($images as $image) {
            $jobs[] = new AnalyzeGalleryImageJob($image);
        }

        $chunkSize = $this->option('chunk');
        $chunks = array_chunk($jobs, $chunkSize);

        $this->withProgressBar($chunks, function ($chunk) {
            Bus::batch($chunk)
                ->name('Gallery Reanalysis Batch')
                ->allowFailures()
                ->onQueue('ai')
                ->dispatch();
        });

        $this->newLine(2);
        $this->info('Tutti i batch sono stati messi in coda con successo!');
        $this->info('Assicurati che i queue worker per la coda "ai" siano attivi: php artisan queue:work --queue=ai');
    }
}
