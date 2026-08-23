<?php

namespace App\Console\Commands;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CreateGalleryFromPosts extends Command
{
    /**
     * Il titolo dell'album e' l'intestazione della gallery pubblica, che esiste
     * anche in inglese: va scritto in entrambe le lingue qui, perche' nessuno
     * lo riscrivera' a mano per i mesi futuri.
     */
    private const MESI_IT = [
        '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
        '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
        '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
        '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre',
    ];

    private const MESI_EN = [
        '01' => 'January', '02' => 'February', '03' => 'March',
        '04' => 'April', '05' => 'May', '06' => 'June',
        '07' => 'July', '08' => 'August', '09' => 'September',
        '10' => 'October', '11' => 'November', '12' => 'December',
    ];

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

        $dryRun = (bool) $this->option('dry-run');
        $from = $this->option('from');

        $posts = Post::where('published_at', '>=', $from)
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'cover'))
            ->with('media', 'categories')
            ->orderBy('published_at')
            ->get();

        $this->info("Post con immagini dal {$from}: {$posts->count()}");

        // Raggruppa per mese (es: "2024-01" → "Gennaio 2024")
        $months = $posts->groupBy(fn ($post) => $post->published_at->format('Y-m'));

        $this->info("Mesi da creare: {$months->count()}");
        $this->newLine();

        $eventiCreati = 0;
        $immaginiCreate = 0;
        $duplicatiSaltati = 0;

        $bar = $this->output->createProgressBar($months->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Inizio...');

        foreach ($months as $yearMonth => $monthPosts) {
            [$year, $month] = explode('-', $yearMonth);
            $title = (self::MESI_IT[$month] ?? $month)." {$year} — News";
            $bar->setMessage($title);

            $category = $this->detectCategory($monthPosts);

            if ($dryRun) {
                $this->newLine();
                $this->info("  📁 {$title} ({$monthPosts->count()} foto, cat: {$category})");
                $eventiCreati++;
                $immaginiCreate += $monthPosts->count();
                $bar->advance();

                continue;
            }

            $event = $this->eventoDelMese($year, $month, $category);

            if ($event->wasRecentlyCreated) {
                $eventiCreati++;
            }

            [$create, $duplicate] = $this->importaLeFotoDelMese($event, $monthPosts, $category);
            $immaginiCreate += $create;
            $duplicatiSaltati += $duplicate;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  REPORT                                                 ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Eventi creati', $eventiCreati],
                ['Immagini create', $immaginiCreate],
                ['Duplicati saltati', $duplicatiSaltati],
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

    /**
     * L'album del mese, riusato se c'e' gia'.
     *
     * Il confronto e' sulla sola chiave italiana del titolo: `title` e'
     * tradotto, quindi in colonna c'e' un JSON per lingua e un
     * `where('title', ...)` non troverebbe piu' nulla, duplicando ogni album a
     * ogni esecuzione.
     */
    private function eventoDelMese(string $year, string $month, string $category): GalleryEvent
    {
        $mese = self::MESI_IT[$month] ?? $month;
        $title = "{$mese} {$year} — News";

        $event = GalleryEvent::query()->where('title->it', $title)->first();

        if ($event) {
            return $event;
        }

        return GalleryEvent::create([
            'title' => ['it' => $title, 'en' => (self::MESI_EN[$month] ?? $month)." {$year} — News"],
            'event_date' => "{$year}-{$month}-01",
            'category' => $category,
            'description' => "Foto dalle news di {$mese} {$year}",
            'is_active' => true,
        ]);
    }

    /**
     * Porta nell'album la copertina di ogni news del mese.
     *
     * @param  Collection<int, Post>  $monthPosts
     * @return array{int, int} immagini create e duplicati saltati
     */
    private function importaLeFotoDelMese(GalleryEvent $event, $monthPosts, string $category): array
    {
        $create = 0;
        $duplicate = 0;

        foreach ($monthPosts as $post) {
            $coverMedia = $post->getFirstMedia('cover');

            if (! $coverMedia) {
                continue;
            }

            // Deduplicazione: hash NON crittografico usato come chiave di confronto,
            // non è un contesto di sicurezza. md5 mantenuto per compatibilità con i
            // file_hash già salvati in DB. (Sonar php:S4790 falso positivo qui.)
            $fileHash = md5($coverMedia->file_name.$coverMedia->size);

            $esiste = GalleryImage::where('file_hash', $fileHash)
                ->where('gallery_event_id', $event->id)
                ->exists();

            if ($esiste) {
                $duplicate++;

                continue;
            }

            $galleryImage = GalleryImage::create([
                'gallery_event_id' => $event->id,
                'title' => mb_substr($this->titoloItaliano($post), 0, 255),
                'category' => $category,
                'sort_order' => $post->published_at->day,
                'file_hash' => $fileHash,
                'is_active' => true,
                'needs_review' => true, // marcato per analisi AI
            ]);

            if ($this->copiaLaCopertina($galleryImage, $coverMedia, $post->id)) {
                $create++;
            }
        }

        return [$create, $duplicate];
    }

    /**
     * Il titolo della news in italiano: `$post->title` darebbe la traduzione
     * della lingua attiva, e l'album e' intitolato in italiano.
     */
    private function titoloItaliano(Post $post): string
    {
        return $post->getTranslation('title', 'it', false) ?: $post->title;
    }

    /**
     * Copia il file dalla collezione `cover` della news a quella `gallery`
     * dell'immagine: stesso disco, copia e non spostamento, cosi' la news
     * conserva la sua copertina.
     */
    private function copiaLaCopertina(GalleryImage $galleryImage, $coverMedia, int $postId): bool
    {
        try {
            $disk = Storage::disk($coverMedia->disk);
            $originalPath = $coverMedia->id.'/'.$coverMedia->file_name;

            if (! $disk->exists($originalPath)) {
                return false;
            }

            $tempFile = sys_get_temp_dir().'/gallery_'.uniqid().'_'.$coverMedia->file_name;
            file_put_contents($tempFile, $disk->get($originalPath));

            $galleryImage->addMedia($tempFile)
                ->usingFileName($coverMedia->file_name)
                ->toMediaCollection('gallery', $coverMedia->disk);

            return true;
        } catch (\Throwable $e) {
            $this->warn("  ⚠ Errore copia media per post {$postId}: ".$e->getMessage());

            return false;
        }
    }
}
