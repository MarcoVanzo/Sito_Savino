<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDoubleEncodedTranslations extends Command
{
    protected $signature = 'fix:double-encoded-translations
        {--dry-run : Mostra cosa verrebbe corretto senza scrivere nulla}';

    protected $description = 'Corregge i campi translatable double-encoded nei post e categorie (bug import WP)';

    private int $fixed = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $this->info('🔧 Fix campi translatable double-encoded');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🏃 MODALITÀ DRY-RUN — nessuna modifica verrà applicata');
            $this->newLine();
        }

        // Fix Post (campi translatable: title, content, excerpt, meta_description)
        $this->info('📝 Correzione Post...');
        $this->fixModel(Post::class, ['title', 'content', 'excerpt', 'meta_description']);

        // Fix Category.name — il modello NON ha HasTranslations, ma il campo è stato
        // salvato come JSON {"it":"..."} dall'import. Dobbiamo estrarre il valore plain.
        $this->info('📁 Correzione Categorie...');
        $this->fixCategoryNames();

        $this->info('🖼️ Correzione Gallery Events e Gallery Images...');
        $this->fixGalleryTitles();

        $this->newLine();
        $this->info("✅ Completato: {$this->fixed} campi corretti, {$this->skipped} già OK");

        return self::SUCCESS;
    }

    /**
     * Corregge i campi translatable di un modello che usa HasTranslations.
     *
     * Il bug: l'import faceva json_encode(['it' => $value]), ma HasTranslations
     * codifica di nuovo, quindi nel DB finisce:
     *   {"it":"{\"it\":\"valore reale\"}"}
     *
     * Il fix estrae il valore interno e lo riscrive correttamente:
     *   {"it":"valore reale"}
     */
    private function fixModel(string $modelClass, array $fields): void
    {
        $model = new $modelClass;

        $modelClass::query()->chunk(100, function ($records) use ($fields) {
            foreach ($records as $record) {
                $changed = false;

                foreach ($fields as $field) {
                    $raw = $record->getRawOriginal($field);
                    if (! $raw) {
                        continue;
                    }

                    // Decodifica il JSON esterno (HasTranslations format)
                    $outer = json_decode($raw, true);
                    if (! is_array($outer)) {
                        continue;
                    }

                    foreach ($outer as $locale => $value) {
                        if (! is_string($value)) {
                            continue;
                        }

                        // Verifica se il valore è a sua volta una stringa JSON {"it":"..."}
                        $inner = json_decode($value, true);
                        if (is_array($inner) && isset($inner[$locale])) {
                            // Double-encoded! Estrai il valore reale
                            $realValue = $inner[$locale];

                            if ($this->option('dry-run')) {
                                $preview = mb_substr($realValue, 0, 60);
                                $this->line("   [DRY] #{$record->id} {$field}.{$locale}: \"{$preview}...\"");
                            }

                            $record->setTranslation($field, $locale, $realValue);
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    if (! $this->option('dry-run')) {
                        $record->save();
                    }
                    $this->fixed++;
                } else {
                    $this->skipped++;
                }
            }
        });

        $this->info("   ✅ {$modelClass}: {$this->fixed} corretti");
    }

    /**
     * Corregge i nomi delle categorie.
     *
     * Category NON usa HasTranslations, ma il campo name è stato salvato come
     * json_encode(['it' => $name]) → '{"it":"Nome"}' nel campo varchar.
     *
     * Estraiamo il valore plain e lo salviamo come stringa semplice.
     */
    private function fixCategoryNames(): void
    {
        $categories = Category::all();
        $catFixed = 0;

        foreach ($categories as $cat) {
            $name = $cat->name;
            if (str_starts_with($name, '{"it":')) {
                $decoded = json_decode($name, true);
                if (is_array($decoded) && isset($decoded['it'])) {
                    $plainName = $decoded['it'];

                    // Controlla se anche il valore interno è JSON double-encoded
                    $inner = json_decode($plainName, true);
                    if (is_array($inner) && isset($inner['it'])) {
                        $plainName = $inner['it'];
                    }

                    if ($this->option('dry-run')) {
                        $this->line("   [DRY] Cat #{$cat->id}: \"{$name}\" → \"{$plainName}\"");
                    } else {
                        $cat->name = $plainName;
                        $cat->save();
                    }
                    $catFixed++;
                }
            }
        }

        $this->info("   ✅ Categorie: {$catFixed} corrette");
    }

    /**
     * Corregge i titoli degli eventi e delle immagini della galleria.
     */
    private function fixGalleryTitles(): void
    {
        $events = \App\Models\GalleryEvent::all();
        $eventFixed = 0;

        foreach ($events as $event) {
            $title = $event->title;
            if ($title && str_starts_with($title, '{"it":')) {
                $decoded = json_decode($title, true);
                if (is_array($decoded) && isset($decoded['it'])) {
                    $plainTitle = $decoded['it'];
                    
                    if ($this->option('dry-run')) {
                        $this->line("   [DRY] GalleryEvent #{$event->id}: \"{$title}\" → \"{$plainTitle}\"");
                    } else {
                        $event->title = $plainTitle;
                        $event->save();
                    }
                    $eventFixed++;
                    $this->fixed++;
                }
            }
        }
        $this->info("   ✅ GalleryEvent: {$eventFixed} corretti");

        $imagesFixed = 0;
        \App\Models\GalleryImage::query()->chunk(200, function ($images) use (&$imagesFixed) {
            foreach ($images as $image) {
                $title = $image->title;
                if ($title && str_starts_with($title, '{"it":')) {
                    $decoded = json_decode($title, true);
                    if (is_array($decoded) && isset($decoded['it'])) {
                        $plainTitle = $decoded['it'];
                        
                        if ($this->option('dry-run')) {
                            $preview = mb_substr($plainTitle, 0, 60);
                            $this->line("   [DRY] GalleryImage #{$image->id}: → \"{$preview}...\"");
                        } else {
                            $image->title = $plainTitle;
                            $image->save();
                        }
                        $imagesFixed++;
                        $this->fixed++;
                    }
                }
            }
        });
        $this->info("   ✅ GalleryImage: {$imagesFixed} corrette");
    }
}
