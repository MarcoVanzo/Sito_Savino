<?php

namespace App\Console\Commands;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CreateGalleryFromPosts extends Command
{
    protected $signature = 'gallery:from-posts
        {--dry-run : Mostra cosa verrebbe creato senza scrivere nulla}
        {--from=2021-07-02 : Data di inizio (YYYY-MM-DD)}';

    protected $description = 'Crea eventi galleria dalle immagini di copertina dei post importati da WordPress';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  Creazione Gallery Events dai Post WordPress            ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $from = $this->option('from');

        // Carica post con immagini, raggruppati per mese
        $posts = Post::where('published_at', '>=', $from)
            ->whereHas('media', function ($q) {
                $q->where('collection_name', 'cover');
            })
            ->with('media', 'categories')
            ->orderBy('published_at')
            ->get();

        $this->info("Post con immagini dal {$from}: {$posts->count()}");

        // Raggruppa per mese (es: "2024-01" → "Gennaio 2024")
        $months = $posts->groupBy(function ($post) {
            return $post->published_at->format('Y-m');
        });

        $this->info("Mesi da creare: {$months->count()}");
        $this->newLine();

        $eventsCreated = 0;
        $imagesCreated = 0;
        $duplicatesSkipped = 0;

        $monthNames = [
            '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
            '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
            '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
            '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre',
        ];

        $bar = $this->output->createProgressBar($months->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Inizio...');

        foreach ($months as $yearMonth => $monthPosts) {
            [$year, $month] = explode('-', $yearMonth);
            $monthName = $monthNames[$month] ?? $month;
            $title = "{$monthName} {$year} — News";
            $bar->setMessage($title);

            // Determina la categoria prevalente
            $category = $this->detectCategory($monthPosts);

            if ($dryRun) {
                $this->newLine();
                $this->info("  📁 {$title} ({$monthPosts->count()} foto, cat: {$category})");
                $eventsCreated++;
                $imagesCreated += $monthPosts->count();
                $bar->advance();

                continue;
            }

            // Crea o riusa l'evento
            $event = GalleryEvent::firstOrCreate(
                ['title' => $title],
                [
                    'event_date' => "{$year}-{$month}-01",
                    'category' => $category,
                    'description' => "Foto dalle news di {$monthName} {$year}",
                    'is_active' => true,
                ]
            );

            $isNew = $event->wasRecentlyCreated;
            if ($isNew) {
                $eventsCreated++;
            }

            // Crea GalleryImage per ogni post con immagine
            foreach ($monthPosts as $post) {
                $coverMedia = $post->getFirstMedia('cover');
                if (! $coverMedia) {
                    continue;
                }

                // Deduplicazione: controlla se esiste già per questo file
                $fileHash = md5($coverMedia->file_name.$coverMedia->size);
                $existing = GalleryImage::where('file_hash', $fileHash)
                    ->where('gallery_event_id', $event->id)
                    ->first();

                if ($existing) {
                    $duplicatesSkipped++;

                    continue;
                }

                // Titolo dalla news
                $imageTitle = is_array($post->getTranslations('title'))
                    ? ($post->getTranslation('title', 'it', false) ?: $post->title)
                    : $post->title;

                // Crea il record GalleryImage
                $galleryImage = GalleryImage::create([
                    'gallery_event_id' => $event->id,
                    'title' => mb_substr($imageTitle, 0, 255),
                    'category' => $category,
                    'sort_order' => $post->published_at->day,
                    'file_hash' => $fileHash,
                    'is_active' => true,
                    'needs_review' => true, // marcato per analisi AI
                ]);

                // Copia il media dalla collection 'cover' del Post alla collection 'gallery' della GalleryImage
                // Usiamo lo stesso file su S3 (copia, non move)
                try {
                    $disk = Storage::disk($coverMedia->disk);
                    $originalPath = $coverMedia->id.'/'.$coverMedia->file_name;

                    if ($disk->exists($originalPath)) {
                        // Scarica e ricarica nella collection gallery
                        $tempFile = sys_get_temp_dir().'/gallery_'.uniqid().'_'.$coverMedia->file_name;
                        file_put_contents($tempFile, $disk->get($originalPath));

                        $galleryImage->addMedia($tempFile)
                            ->usingFileName($coverMedia->file_name)
                            ->toMediaCollection('gallery', $coverMedia->disk);

                        $imagesCreated++;
                    }
                } catch (\Throwable $e) {
                    $this->warn("  ⚠ Errore copia media per post {$post->id}: ".$e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Report
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  REPORT                                                 ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Eventi creati', $eventsCreated],
                ['Immagini create', $imagesCreated],
                ['Duplicati saltati', $duplicatesSkipped],
            ]
        );

        if ($dryRun) {
            $this->warn('ℹ️ Dry-run completato. Per eseguire davvero, rimuovi --dry-run');
        }

        return self::SUCCESS;
    }

    /**
     * Determina la categoria prevalente tra i post del mese.
     */
    private function detectCategory($posts): string
    {
        $categoryNames = [];
        foreach ($posts as $post) {
            foreach ($post->categories as $cat) {
                $name = $cat->name;
                if (is_string($name) && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    $name = $decoded['it'] ?? $name;
                }
                $categoryNames[] = strtolower($name);
            }
        }

        if (empty($categoryNames)) {
            return 'Partite';
        }

        $counts = array_count_values($categoryNames);
        arsort($counts);
        $top = array_key_first($counts);

        // Mappa alle categorie della gallery
        return match (true) {
            str_contains($top, 'champions') => 'Eventi',
            str_contains($top, 'coppa') => 'Eventi',
            str_contains($top, 'mondiale') => 'Eventi',
            str_contains($top, 'sponsor') => 'Eventi',
            str_contains($top, 'societ') => 'Backstage',
            default => 'Partite',
        };
    }
}
