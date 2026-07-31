<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corregge i campi tradotti codificati due volte dall'import da WordPress.
 *
 * L'import faceva `json_encode(['it' => $valore])` su un campo che il trait
 * HasTranslations codifica di nuovo, quindi nel database finiva:
 *
 *   {"it":"{\"it\":\"testo reale\"}"}
 *
 * invece di `{"it":"testo reale"}`. Il sito mostrava il JSON interno come se
 * fosse il testo.
 *
 * Era un comando artisan eseguito da start.sh a ogni avvio del container: una
 * correzione una-tantum travestita da passo di boot, che rileggeva quattro
 * tabelle a ogni deploy per non trovarci quasi mai niente. Qui diventa quello
 * che è sempre stata, una migrazione dati.
 *
 * Volutamente scritta su query grezze e non sui model: una migrazione deve
 * continuare a funzionare anche quando i model cambiano, e questa tocca campi
 * (`categories.name`) il cui model non usa nemmeno HasTranslations.
 *
 * `down()` è un no-op: l'informazione persa nella doppia codifica non è
 * ricostruibile, e ricodificare all'indietro rimetterebbe il difetto.
 */
return new class extends Migration
{
    /**
     * Campi translatable: nel database sono un JSON per lingua.
     *
     * @var array<string, list<string>>
     */
    private const TRANSLATABLE = [
        'posts' => ['title', 'content', 'excerpt', 'meta_description'],
    ];

    /**
     * Campi in testo semplice che l'import ha riempito con un JSON per lingua.
     *
     * @var array<string, list<string>>
     */
    private const PLAIN = [
        'categories' => ['name'],
        'gallery_events' => ['title'],
        'gallery_images' => ['title'],
    ];

    public function up(): void
    {
        foreach (self::TRANSLATABLE as $table => $columns) {
            $this->fixTranslatable($table, $columns);
        }

        foreach (self::PLAIN as $table => $columns) {
            $this->fixPlain($table, $columns);
        }
    }

    public function down(): void
    {
        // Irreversibile per costruzione: vedi il commento in testa.
    }

    /**
     * Sfila il livello di codifica in eccesso lasciando il JSON per lingua.
     *
     * @param  list<string>  $columns
     */
    private function fixTranslatable(string $table, array $columns): void
    {
        if (! $this->tableHasColumns($table, $columns)) {
            return;
        }

        DB::table($table)
            ->select(array_merge(['id'], $columns))
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $decoded = json_decode((string) $row->{$column}, true);

                        if (! is_array($decoded)) {
                            continue;
                        }

                        $changed = false;

                        foreach ($decoded as $locale => $value) {
                            if (! is_string($value)) {
                                continue;
                            }

                            $inner = json_decode($value, true);

                            // Si sfila solo se il valore interno contiene la
                            // stessa lingua: un testo che per caso è JSON valido
                            // (un frammento di configurazione in un articolo)
                            // non deve essere toccato.
                            if (is_array($inner) && isset($inner[$locale]) && is_string($inner[$locale])) {
                                $decoded[$locale] = $inner[$locale];
                                $changed = true;
                            }
                        }

                        if ($changed) {
                            $updates[$column] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    /**
     * Riporta a testo semplice i campi in cui è finito un JSON per lingua.
     *
     * @param  list<string>  $columns
     */
    private function fixPlain(string $table, array $columns): void
    {
        if (! $this->tableHasColumns($table, $columns)) {
            return;
        }

        $locales = (array) config('app.supported_locales', ['it', 'en']);

        DB::table($table)
            ->select(array_merge(['id'], $columns))
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns, $locales) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = (string) $row->{$column};

                        if ($value === '' || ! str_starts_with($value, '{"')) {
                            continue;
                        }

                        $decoded = json_decode($value, true);

                        if (! is_array($decoded)) {
                            continue;
                        }

                        // Si prende la prima lingua supportata presente. L'ordine
                        // di `supported_locales` mette l'italiano per primo, che
                        // è la lingua in cui l'import ha scritto tutto.
                        foreach ($locales as $locale) {
                            if (! isset($decoded[$locale]) || ! is_string($decoded[$locale])) {
                                continue;
                            }

                            $plain = $decoded[$locale];

                            // Può essere codificato due volte anche qui.
                            $inner = json_decode($plain, true);
                            if (is_array($inner) && isset($inner[$locale]) && is_string($inner[$locale])) {
                                $plain = $inner[$locale];
                            }

                            $updates[$column] = $plain;
                            break;
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    /**
     * @param  list<string>  $columns
     */
    private function tableHasColumns(string $table, array $columns): bool
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
};
