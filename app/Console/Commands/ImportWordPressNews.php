<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\WordPressContentCleaner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskCannotBeAccessed;

class ImportWordPressNews extends Command
{
    protected $signature = 'import:wp-news
        {--dry-run : Mostra cosa verrebbe importato senza scrivere nulla}
        {--force : Sovrascrive post con stesso wp_id}
        {--limit=0 : Limita il numero di post da importare (0 = tutti)}
        {--from=2025-07-02 : Data di inizio filtro (YYYY-MM-DD)}
        {--skip-images : Salta il download delle immagini}
        {--export-path= : Percorso base dell\'export WordPress}';

    protected $description = 'Importa le news dall\'export WordPress del vecchio sito savinodelbenevolley.it';

    private WordPressContentCleaner $cleaner;

    private string $exportPath;

    private string $mediaDisk;

    /** @var array<string, int> Mapping WP category ID → nuovo Category ID */
    private array $categoryMap = [];

    /** @var array<string, int> Mapping WP tag ID → nuovo Tag ID */
    private array $tagMap = [];

    /** @var array<int, array> Media map: WP media ID → media data */
    private array $mediaMap = [];

    /** Contatori per il report finale */
    private int $imported = 0;

    private int $skipped = 0;

    private int $imagesFailed = 0;

    private int $imagesUploaded = 0;

    /** Tag da escludere (generici, ridondanti, spazzatura) */
    private array $excludedTagSlugs = [
        'savino-del-bene-volley', 'savino-del-bene-scandicci', 'savino-del-bene',
        'pallavolo', 'volley', 'volleyball', 'serie-a1', 'femminile',
    ];

    /** Tag numerici/spazzatura pattern */
    private array $excludedTagPatterns = [
        '/^\d{4}$/',           // "2025", "2026"
        '/^\d{4}-\d{4}$/',     // "2025-2026"
        '/^\d+-\d+$/',         // "3-0", "3-1"
        '/^[a-z]\d+$/i',       // "A1"
    ];

    public function handle(): int
    {
        $this->cleaner = new WordPressContentCleaner;
        $this->exportPath = $this->option('export-path') ?: '/Users/marcovanzo/wp_export_savino/data';
        $this->mediaDisk = $this->detectMediaDisk();

        // L'SDK AWS + Spatie + i post filtrati richiedono più dei 128MB di default
        ini_set('memory_limit', '512M');

        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  Import News WordPress → Savino Del Bene Volley         ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🏃 MODALITÀ DRY-RUN — nessuna modifica verrà applicata');
            $this->newLine();
        }

        // Verifica file sorgente
        if (! $this->verifySourceFiles()) {
            return self::FAILURE;
        }

        // Verifica configurazione immagini
        $skipImages = $this->option('skip-images');
        if (! $skipImages) {
            $this->checkImageConfig();
        }

        // Carica dati sorgente
        $this->info('📂 Caricamento dati export...');
        $posts = $this->loadAndFilterPosts();
        $this->loadMediaMap();
        $categories = $this->loadJson('categories.json');
        $tags = $this->loadJson('tags.json');

        $this->info(sprintf('   Post filtrati: %d', count($posts)));
        $this->newLine();

        // Prepara la lista degli slug per la riscrittura dei link interni
        $allSlugs = array_map(fn ($p) => $p['slug'], $posts);
        $this->cleaner->setImportedSlugs($allSlugs);

        // Fase 1: Importa categorie
        $this->info('📁 Fase 1: Import categorie...');
        $this->importCategories($posts, $categories);

        // Fase 2: Importa tag
        $this->info('🏷️  Fase 2: Import tag...');
        $this->importTags($posts, $tags);

        // Fase 3: Importa post
        $this->info('📝 Fase 3: Import post...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($posts));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Inizio...');
        $bar->start();

        foreach ($posts as $wpPost) {
            $this->importPost($wpPost, $skipImages, $bar);
            $bar->advance();
        }

        $bar->setMessage('Completato!');
        $bar->finish();
        $this->newLine(2);

        // Report finale
        $this->printReport();

        return self::SUCCESS;
    }

    /**
     * Verifica che i file sorgente esistano.
     */
    private function verifySourceFiles(): bool
    {
        $requiredFiles = ['posts.json', 'categories.json', 'tags.json', 'media.json'];
        $missing = [];

        foreach ($requiredFiles as $file) {
            if (! file_exists($this->exportPath.'/'.$file)) {
                $missing[] = $file;
            }
        }

        if (! empty($missing)) {
            $this->error('File mancanti in '.$this->exportPath.': '.implode(', ', $missing));

            return false;
        }

        $this->info('✅ File sorgente verificati');

        return true;
    }

    /**
     * Determina quale disk usare per le immagini e ne verifica l'accessibilità.
     */
    private function detectMediaDisk(): string
    {
        $disk = config('media-library.disk_name', 'public');

        // Se è configurato come s3, verifica le credenziali
        if ($disk === 's3') {
            $key = config('filesystems.disks.s3.key');
            $bucket = config('filesystems.disks.s3.bucket');

            if (! $key || ! $bucket) {
                $this->warn('⚠️  MEDIA_DISK è impostato su s3 ma le credenziali AWS sono vuote.');
                $this->warn('   Le immagini verranno saltate. Configura AWS_* nel .env.');

                return 'none';
            }
        }

        return $disk;
    }

    /**
     * Verifica la configurazione per le immagini.
     */
    private function checkImageConfig(): void
    {
        $s3Key = config('filesystems.disks.s3.key');
        $s3Bucket = config('filesystems.disks.s3.bucket');

        if ($s3Key && $s3Bucket) {
            $this->info('✅ Credenziali S3 configurate — immagini su DO Spaces');
        } elseif ($this->mediaDisk === 'public') {
            $this->warn('⚠️  MEDIA_DISK=public — immagini in storage locale (NON su DO Spaces)');
            $this->warn('   Per caricare su DO Spaces, configura AWS_* e MEDIA_DISK=s3 nel .env');
        } else {
            $this->warn('⚠️  S3 non configurato — le immagini verranno saltate');
        }
        $this->newLine();
    }

    /**
     * Carica e filtra i post dall'export.
     *
     * Usa un pre-filtro Python per evitare di caricare
     * l'intero posts.json (76 MB) in memoria PHP.
     * Estrae solo i campi necessari per ridurre il payload.
     */
    private function loadAndFilterPosts(): array
    {
        $from = $this->option('from');
        $postsFile = $this->exportPath.'/posts.json';
        $tempFile = sys_get_temp_dir().'/wp_import_filtered_posts.json';

        // Python estrae SOLO i campi necessari e filtra per data
        $pythonScript = <<<'PYTHON'
import json, sys

from_date = sys.argv[1]
src = sys.argv[2]
dst = sys.argv[3]

with open(src, 'r') as f:
    posts = json.load(f)

filtered = []
for p in posts:
    if p.get('date', '') >= from_date:
        yoast = p.get('yoast_head_json', {}) or {}
        filtered.append({
            'id': p['id'],
            'date': p.get('date', ''),
            'slug': p.get('slug', ''),
            'status': p.get('status', ''),
            'title': p.get('title', {}),
            'content': p.get('content', {}),
            'excerpt': p.get('excerpt', {}),
            'categories': p.get('categories', []),
            'tags': p.get('tags', []),
            'featured_media': p.get('featured_media', 0),
            'og_title': yoast.get('og_title', ''),
            'og_description': yoast.get('og_description', ''),
        })

filtered.sort(key=lambda x: x.get('date', ''))

with open(dst, 'w') as f:
    json.dump(filtered, f, ensure_ascii=False)

print(f'OK:{len(filtered)}')
PYTHON;

        $scriptFile = sys_get_temp_dir().'/wp_import_filter.py';
        file_put_contents($scriptFile, $pythonScript);

        $cmd = sprintf(
            'python3 %s %s %s %s 2>&1',
            escapeshellarg($scriptFile),
            escapeshellarg($from.'T00:00:00'),
            escapeshellarg($postsFile),
            escapeshellarg($tempFile)
        );

        exec($cmd, $output, $exitCode);
        @unlink($scriptFile);

        if ($exitCode !== 0 || ! file_exists($tempFile)) {
            $this->error('Errore pre-filtro Python: '.implode("\n", $output));
            $this->error('Fallback: caricamento diretto con memory_limit aumentato...');

            $oldLimit = ini_set('memory_limit', '512M');
            $posts = $this->loadJson('posts.json');
            ini_set('memory_limit', $oldLimit ?: '128M');

            $filtered = array_filter($posts, fn ($p) => ($p['date'] ?? '') >= $from.'T00:00:00');
            usort($filtered, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));
            unset($posts);

            $limit = (int) $this->option('limit');
            if ($limit > 0) {
                $filtered = array_slice($filtered, 0, $limit);
                $this->warn("   ⚡ Limitato a {$limit} post");
            }

            return array_values($filtered);
        }

        $this->info('   '.trim(implode("\n", $output)));

        $oldLimit = ini_set('memory_limit', '384M');
        $filtered = json_decode(file_get_contents($tempFile), true);
        ini_set('memory_limit', $oldLimit ?: '128M');
        @unlink($tempFile);

        // Applica limite
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $filtered = array_slice($filtered, 0, $limit);
            $this->warn("   ⚡ Limitato a {$limit} post");
        }

        return $filtered;
    }

    /**
     * Carica la mappa dei media.
     * media.json è ~11 MB — carichiamo solo i campi necessari.
     */
    private function loadMediaMap(): void
    {
        $tempFile = sys_get_temp_dir().'/wp_import_media_map.json';
        $mediaFile = $this->exportPath.'/media.json';

        // Usa Python per estrarre solo i campi necessari (riduce la memoria)
        $pythonScript = sprintf(
            'import json; media=json.load(open(%s)); result={m["id"]:{"source_url":m.get("source_url","")} for m in media}; json.dump(result,open(%s,"w"))',
            escapeshellarg($mediaFile),
            escapeshellarg($tempFile)
        );

        exec('python3 -c '.escapeshellarg($pythonScript).' 2>&1', $output, $exitCode);

        if ($exitCode === 0 && file_exists($tempFile)) {
            $map = json_decode(file_get_contents($tempFile), true);
            @unlink($tempFile);
            foreach ($map as $id => $data) {
                $this->mediaMap[(int) $id] = $data;
            }
        } else {
            // Fallback
            $oldLimit = ini_set('memory_limit', '512M');
            $media = $this->loadJson('media.json');
            ini_set('memory_limit', $oldLimit);
            foreach ($media as $m) {
                $this->mediaMap[$m['id']] = ['source_url' => $m['source_url'] ?? ''];
            }
            unset($media);
        }

        $this->info(sprintf('   Media caricati: %d', count($this->mediaMap)));
    }

    /**
     * Importa le categorie usate dai post filtrati.
     */
    private function importCategories(array $posts, array $wpCategories): void
    {
        // Raccogli tutti gli ID categoria usati
        $usedCategoryIds = [];
        foreach ($posts as $p) {
            foreach ($p['categories'] ?? [] as $catId) {
                $usedCategoryIds[$catId] = true;
            }
        }

        // Costruisci mappa WP ID → dati
        $wpCatMap = [];
        foreach ($wpCategories as $cat) {
            $wpCatMap[$cat['id']] = $cat;
        }

        $created = 0;
        $existing = 0;

        foreach (array_keys($usedCategoryIds) as $wpCatId) {
            $wpCat = $wpCatMap[$wpCatId] ?? null;
            if (! $wpCat) {
                $this->warn("   ⚠️  Categoria WP ID {$wpCatId} non trovata nell'export");

                continue;
            }

            $name = html_entity_decode($wpCat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = $wpCat['slug'];

            // Rinomina "Senza categoria" → "Notizie"
            if ($slug === 'senza-categoria') {
                $name = 'Notizie';
                $slug = 'notizie';
            }

            if ($this->option('dry-run')) {
                $this->line("   [DRY] Categoria: \"{$name}\" (slug: {$slug})");
                $this->categoryMap[$wpCatId] = 0;

                continue;
            }

            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'wp_id' => $wpCatId,
                    'name' => json_encode(['it' => $name], JSON_UNESCAPED_UNICODE),
                    'slug' => $slug,
                ]
            );

            if ($category->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }

            $this->categoryMap[$wpCatId] = $category->id;
        }

        $this->info(sprintf('   ✅ Categorie: %d create, %d esistenti', $created, $existing));
    }

    /**
     * Importa i tag usati dai post filtrati (con filtro qualità).
     */
    private function importTags(array $posts, array $wpTags): void
    {
        // Raccogli tutti gli ID tag usati
        $usedTagIds = [];
        foreach ($posts as $p) {
            foreach ($p['tags'] ?? [] as $tagId) {
                $usedTagIds[$tagId] = true;
            }
        }

        // Costruisci mappa WP ID → dati
        $wpTagMap = [];
        foreach ($wpTags as $tag) {
            $wpTagMap[$tag['id']] = $tag;
        }

        $created = 0;
        $existing = 0;
        $excluded = 0;

        foreach (array_keys($usedTagIds) as $wpTagId) {
            $wpTag = $wpTagMap[$wpTagId] ?? null;
            if (! $wpTag) {
                continue;
            }

            $name = html_entity_decode($wpTag['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = $wpTag['slug'];

            // Filtro qualità: escludi tag generici e spazzatura
            if ($this->isExcludedTag($name, $slug)) {
                $excluded++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->tagMap[$wpTagId] = 0;

                continue;
            }

            $tag = Tag::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'slug' => $slug,
                ]
            );

            if ($tag->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }

            $this->tagMap[$wpTagId] = $tag->id;
        }

        $this->info(sprintf('   ✅ Tag: %d creati, %d esistenti, %d esclusi (spazzatura/generici)', $created, $existing, $excluded));
    }

    /**
     * Verifica se un tag è da escludere (generico, ridondante, spazzatura).
     */
    private function isExcludedTag(string $name, string $slug): bool
    {
        // Escludi per slug
        if (in_array($slug, $this->excludedTagSlugs)) {
            return true;
        }

        // Escludi per pattern (numeri, punteggi, ecc.)
        foreach ($this->excludedTagPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        // Escludi tag con meno di 3 caratteri
        if (mb_strlen(trim($name)) < 3) {
            return true;
        }

        return false;
    }

    /**
     * Importa un singolo post.
     */
    private function importPost(array $wpPost, bool $skipImages, $bar): void
    {
        $wpId = $wpPost['id'];
        $rawTitle = is_array($wpPost['title']) ? ($wpPost['title']['rendered'] ?? '') : $wpPost['title'];
        $title = $this->cleaner->cleanTitle($rawTitle);

        $bar->setMessage(Str::limit($title, 50));

        // Verifica se già importato
        if (! $this->option('force')) {
            $existing = Post::where('wp_id', $wpId)->first();
            if ($existing) {
                $this->skipped++;

                return;
            }
        }

        // Slug
        $slug = $wpPost['slug'];
        if (! $this->cleaner->isValidSlug($slug)) {
            $slug = $this->cleaner->generateSlug($title);
        }

        // Assicura unicità slug
        $slug = $this->ensureUniqueSlug($slug, $wpId);

        // Contenuto
        $rawContent = is_array($wpPost['content']) ? ($wpPost['content']['rendered'] ?? '') : $wpPost['content'];
        $cleanContent = $this->cleaner->clean($rawContent);

        // Excerpt
        $rawExcerpt = is_array($wpPost['excerpt']) ? ($wpPost['excerpt']['rendered'] ?? '') : $wpPost['excerpt'];
        $cleanExcerpt = $this->cleaner->cleanExcerpt($rawExcerpt, $rawContent);

        // Meta SEO (già estratti dal pre-filtro Python)
        $metaTitle = ! empty($wpPost['og_title']) ? $this->cleaner->cleanTitle($wpPost['og_title']) : null;
        $metaDesc = $wpPost['og_description'] ?? null;
        if ($metaDesc) {
            $metaDesc = html_entity_decode($metaDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Data pubblicazione
        $publishedAt = $wpPost['date'] ?? null;
        if ($publishedAt) {
            $publishedAt = str_replace('T', ' ', $publishedAt);
        }

        if ($this->option('dry-run')) {
            $this->imported++;

            return;
        }

        // Crea o aggiorna il post
        DB::beginTransaction();
        try {
            $postData = [
                'wp_id' => $wpId,
                'title' => json_encode(['it' => $title], JSON_UNESCAPED_UNICODE),
                'slug' => $slug,
                'content' => json_encode(['it' => $cleanContent], JSON_UNESCAPED_UNICODE),
                'excerpt' => json_encode(['it' => $cleanExcerpt], JSON_UNESCAPED_UNICODE),
                'status' => PostStatus::Published->value,
                'published_at' => $publishedAt,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDesc ? json_encode(['it' => $metaDesc], JSON_UNESCAPED_UNICODE) : null,
            ];

            if ($this->option('force')) {
                $post = Post::updateOrCreate(['wp_id' => $wpId], $postData);
            } else {
                $post = Post::create($postData);
            }

            // Associa categorie
            $categoryIds = [];
            foreach ($wpPost['categories'] ?? [] as $wpCatId) {
                if (isset($this->categoryMap[$wpCatId]) && $this->categoryMap[$wpCatId] > 0) {
                    $categoryIds[] = $this->categoryMap[$wpCatId];
                }
            }
            if (! empty($categoryIds)) {
                $post->categories()->sync($categoryIds);
            }

            // Associa tag
            $tagIds = [];
            foreach ($wpPost['tags'] ?? [] as $wpTagId) {
                if (isset($this->tagMap[$wpTagId]) && $this->tagMap[$wpTagId] > 0) {
                    $tagIds[] = $this->tagMap[$wpTagId];
                }
            }
            if (! empty($tagIds)) {
                $post->tags()->sync($tagIds);
            }

            // Immagine in evidenza
            if (! $skipImages && $this->mediaDisk !== 'none') {
                $this->attachFeaturedImage($post, $wpPost);
            }

            DB::commit();
            $this->imported++;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("   ❌ Errore post \"{$title}\": ".$e->getMessage());
            $this->skipped++;
        }
    }

    /**
     * Scarica e associa l'immagine in evidenza.
     */
    private function attachFeaturedImage(Post $post, array $wpPost): void
    {
        $featuredMediaId = $wpPost['featured_media'] ?? 0;
        if (! $featuredMediaId) {
            return;
        }

        $mediaData = $this->mediaMap[$featuredMediaId] ?? null;
        if (! $mediaData) {
            $this->imagesFailed++;

            return;
        }

        $sourceUrl = $mediaData['source_url'] ?? '';
        if (! $sourceUrl) {
            $this->imagesFailed++;

            return;
        }

        // Cerca il file nella cartella locale media_files
        $filename = basename(parse_url($sourceUrl, PHP_URL_PATH));
        $localPath = $this->exportPath.'/media_files/'.$filename;

        if (! file_exists($localPath)) {
            $this->imagesFailed++;

            return;
        }

        try {
            // Pulisci media esistenti se --force
            if ($this->option('force') && $post->getFirstMedia('cover')) {
                $post->clearMediaCollection('cover');
            }

            // Carica l'immagine sul disk configurato (s3 o public)
            $post->addMedia($localPath)
                ->preservingOriginal()
                ->usingFileName($filename)
                ->toMediaCollection('cover', $this->mediaDisk);

            $this->imagesUploaded++;

        } catch (DiskCannotBeAccessed $e) {
            $this->imagesFailed++;
            // Non bloccare l'import — logga e prosegui
        } catch (\Throwable $e) {
            $this->imagesFailed++;
        }
    }

    /**
     * Assicura che lo slug sia unico nel database.
     */
    private function ensureUniqueSlug(string $slug, int $wpId): string
    {
        $existing = Post::where('slug', $slug)->where('wp_id', '!=', $wpId)->exists();
        if (! $existing) {
            return $slug;
        }

        $counter = 2;
        while (Post::where('slug', $slug.'-'.$counter)->exists()) {
            $counter++;
        }

        return $slug.'-'.$counter;
    }

    /**
     * Stampa il report finale.
     */
    private function printReport(): void
    {
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  REPORT IMPORTAZIONE                                    ║');
        $this->info('╠══════════════════════════════════════════════════════════╣');
        $this->info(sprintf('║  ✅ Post importati:      %4d                            ║', $this->imported));
        $this->info(sprintf('║  ⏭️  Post saltati:        %4d                            ║', $this->skipped));
        $this->info(sprintf('║  🖼️  Immagini caricate:   %4d                            ║', $this->imagesUploaded));
        $this->info(sprintf('║  ⚠️  Immagini fallite:    %4d                            ║', $this->imagesFailed));
        $this->info(sprintf('║  📁 Categorie mappate:   %4d                            ║', count($this->categoryMap)));
        $this->info(sprintf('║  🏷️  Tag mappati:         %4d                            ║', count($this->tagMap)));
        $this->info('╚══════════════════════════════════════════════════════════╝');

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('ℹ️  Dry-run completato. Per eseguire davvero, rimuovi --dry-run');
        }
    }

    /**
     * Carica un file JSON dall'export.
     */
    private function loadJson(string $filename): array
    {
        $path = $this->exportPath.'/'.$filename;
        $content = file_get_contents($path);

        return json_decode($content, true);
    }
}
