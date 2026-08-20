<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StaffMember;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Popola le traduzioni inglesi dei contenuti gestiti dal CMS.
 *
 * Gran parte del sito era pubblicata solo in italiano, e dove la scheda inglese
 * risultava compilata lo era con una copia del testo italiano: /en mostrava
 * quindi contenuti italiani. Il comando riempie la sola chiave `en` usando la
 * mappa in database/data/content_translations_en.php.
 *
 * È idempotente e non distruttivo: salta le righe la cui traduzione inglese è
 * già diversa dall'italiano, perché quelle sono state tradotte in redazione.
 * Rieseguirlo dopo che la redazione ha aggiunto contenuti nuovi elenca ciò che
 * resta da tradurre, senza toccare il resto.
 *
 * @phpstan-type TranslatableModel Product|ProductCategory|StaffMember|MenuItem|HeroSlide|Category|Page
 */
class TranslateContent extends Command
{
    protected $signature = 'content:translate-missing {--dry-run : Mostra le modifiche senza scriverle}';

    protected $description = 'Popola le traduzioni inglesi mancanti dei contenuti CMS, senza toccare quelle già fatte in redazione';

    /**
     * Entità tradotte cercando il testo italiano esatto, con i rispettivi campi.
     * La chiave nel file dati è "<tabella>.<campo>".
     *
     * @var array<class-string<TranslatableModel>, list<string>>
     */
    private const BY_TEXT = [
        Product::class => ['name', 'short_description', 'description'],
        ProductCategory::class => ['name'],
        StaffMember::class => ['role'],
        MenuItem::class => ['label'],
        HeroSlide::class => ['title'],
        Category::class => ['name'],
    ];

    /**
     * Entità tradotte individuando la riga da una colonna identificativa.
     * Serve per i testi lunghi, dove pretendere il match esatto di centinaia di
     * caratteri di HTML sarebbe fragile.
     *
     * @var array<class-string<Page>, string>
     */
    private const BY_KEY = [
        Page::class => 'slug',
    ];

    private int $updated = 0;

    private int $skipped = 0;

    /** @var list<string> */
    private array $missing = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $path = database_path('data/content_translations_en.php');

        if (! is_file($path)) {
            $this->error("File delle traduzioni non trovato: {$path}");

            return self::FAILURE;
        }

        /** @var array{byText?: array<string, list<array{it: string, en: string}>>, byKey?: array<string, array<string, array<string, string>>>} $raw */
        $raw = require $path;

        $byText = $this->indexByText($raw['byText'] ?? []);
        $byKey = $raw['byKey'] ?? [];

        foreach (self::BY_TEXT as $modelClass => $fields) {
            foreach ($modelClass::query()->cursor() as $model) {
                $this->translateByText($model, $fields, $byText, $dryRun);
            }
        }

        foreach (self::BY_KEY as $modelClass => $keyColumn) {
            foreach ($modelClass::query()->cursor() as $model) {
                $this->translateByKey($model, $keyColumn, $byKey, $dryRun);
            }
        }

        if (! $dryRun && $this->updated > 0) {
            $this->forgetRelatedProductsCache();
        }

        return $this->report($dryRun);
    }

    /**
     * Converte le coppie it/en in mappe di lookup
     * "<tabella>.<campo>" => [testo italiano => testo inglese].
     *
     * @param  array<string, list<array{it: string, en: string}>>  $raw
     * @return array<string, array<string, string>>
     */
    private function indexByText(array $raw): array
    {
        $map = [];

        foreach ($raw as $key => $pairs) {
            foreach ($pairs as $pair) {
                $map[$key][$pair['it']] = $pair['en'];
            }
        }

        return $map;
    }

    /**
     * @param  TranslatableModel  $model
     * @param  list<string>  $fields
     * @param  array<string, array<string, string>>  $map
     */
    private function translateByText(Model $model, array $fields, array $map, bool $dryRun): void
    {
        $table = $model->getTable();

        foreach ($fields as $field) {
            $italian = $this->translationOf($model, $field, 'it');

            if (trim($italian) === '') {
                continue;
            }

            if ($this->alreadyTranslated($model, $field, $italian)) {
                continue;
            }

            $translation = $map["{$table}.{$field}"][$italian] ?? null;

            if ($translation === null) {
                $this->missing[] = sprintf('%s #%d %s', class_basename($model), $model->getKey(), $field);

                continue;
            }

            $this->applyTranslation($model, $field, $translation);
        }

        $this->persist($model, $dryRun);
    }

    /**
     * @param  Page  $model
     * @param  array<string, array<string, array<string, string>>>  $map
     */
    private function translateByKey(Model $model, string $keyColumn, array $map, bool $dryRun): void
    {
        $rows = $map[$model->getTable()] ?? [];
        $translations = $rows[(string) $model->getAttribute($keyColumn)] ?? null;

        if ($translations === null) {
            return;
        }

        foreach ($translations as $field => $translation) {
            $italian = $this->translationOf($model, $field, 'it');

            // Un campo vuoto in italiano non va riempito in inglese: la pagina
            // mostrerebbe una sezione che nella versione originale non esiste.
            if (trim($italian) === '' || $this->alreadyTranslated($model, $field, $italian)) {
                continue;
            }

            $this->applyTranslation($model, $field, $translation);
        }

        $this->persist($model, $dryRun);
    }

    /**
     * @param  TranslatableModel  $model
     */
    private function translationOf(Model $model, string $field, string $locale): string
    {
        $value = $model->getTranslation($field, $locale, false);

        return is_string($value) ? $value : '';
    }

    /**
     * Vero se la redazione ha già scritto una traduzione inglese vera, cioè
     * diversa dall'italiano. In quel caso non la tocchiamo mai.
     *
     * @param  TranslatableModel  $model
     */
    private function alreadyTranslated(Model $model, string $field, string $italian): bool
    {
        $english = $this->translationOf($model, $field, 'en');

        if ($english === '' || $english === $italian) {
            return false;
        }

        $this->skipped++;

        return true;
    }

    /**
     * @param  TranslatableModel  $model
     */
    private function applyTranslation(Model $model, string $field, string $translation): void
    {
        $model->setTranslation($field, 'en', $translation);
        $this->updated++;
    }

    private function persist(Model $model, bool $dryRun): void
    {
        if ($model->isDirty() && ! $dryRun) {
            $model->save();
        }
    }

    /**
     * Il CacheInvalidationObserver ripulisce solo "public:shop": le card dei
     * prodotti correlati hanno una chiave per prodotto e per lingua e
     * resterebbero in italiano fino alla scadenza (30 minuti).
     */
    private function forgetRelatedProductsCache(): void
    {
        $locales = config('app.supported_locales', ['it', 'en']);

        foreach (Product::query()->pluck('id') as $id) {
            foreach ($locales as $locale) {
                Cache::forget("product:{$id}:related:{$locale}");
            }
        }
    }

    private function report(bool $dryRun): int
    {
        if ($this->missing !== []) {
            $this->warn(sprintf('Traduzione mancante per %d campi:', count($this->missing)));

            foreach ($this->missing as $item) {
                $this->line("  - {$item}");
            }

            $this->warn('Aggiungere le voci corrispondenti in database/data/content_translations_en.php.');
        }

        $verb = $dryRun ? 'Da tradurre' : 'Tradotti';
        $this->info(sprintf(
            '%s: %d campi. Già tradotti in redazione (saltati): %d. Mancanti: %d.',
            $verb,
            $this->updated,
            $this->skipped,
            count($this->missing),
        ));

        if ($dryRun) {
            $this->comment('Esecuzione in dry-run: nessuna scrittura sul database.');
        }

        return $this->missing === [] ? self::SUCCESS : self::FAILURE;
    }
}
