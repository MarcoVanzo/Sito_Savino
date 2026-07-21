<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le colonne tradotte con spatie/laravel-translatable contengono un JSON con
 * una voce per lingua ({"it":"…","en":"…"}), ma erano dichiarate varchar(255):
 * con entrambe le lingue valorizzate si arriva all'errore MySQL 1406
 * "Data too long", che in produzione si manifesta come salvataggio fallito dal
 * CMS. Le stesse colonne su shipping_zones e auctions erano già state convertite.
 *
 * Si usa `text` e non `json`: le migrazioni girano a ogni avvio del container
 * (start.sh → migrate --force), e con tipo `json` una sola riga legacy in testo
 * semplice farebbe fallire l'ALTER, bloccando l'avvio dell'applicazione.
 * `text` toglie il limite dei 255 caratteri senza alcun vincolo di validità.
 */
return new class extends Migration
{
    /**
     * Colonne da convertire, per tabella, con la relativa nullabilità.
     *
     * @var array<string, array<string, bool>>
     */
    private const COLUMNS = [
        'menu_items' => [
            'label' => false,
            'description' => true,
            'motto_title' => true,
            'motto_subtitle' => true,
        ],
        'hero_slides' => [
            'title' => false,
            'subtitle' => true,
            'cta_text' => true,
        ],
        'staff_members' => [
            'role' => false,
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $toChange = array_filter(
                $columns,
                fn (bool $nullable, string $column) => $this->dataType($table, $column) === 'varchar',
                ARRAY_FILTER_USE_BOTH
            );

            if ($toChange === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($toChange) {
                foreach ($toChange as $column => $nullable) {
                    $blueprint->text($column)->nullable($nullable)->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $nullable) {
                if ($this->dataType($table, $column) !== 'text') {
                    continue;
                }

                // Tornare a varchar(255) tronca silenziosamente i valori più
                // lunghi: è esattamente il dato che questa migrazione esiste per
                // salvare. Meglio fallire in modo esplicito che perdere le
                // traduzioni.
                $longest = DB::table($table)->max(DB::raw("CHAR_LENGTH(`{$column}`)"));

                if ($longest > 255) {
                    throw new RuntimeException(
                        "Rollback annullato: {$table}.{$column} contiene valori da {$longest} "
                        .'caratteri, che varchar(255) troncherebbe. Ridurre i contenuti prima di procedere.'
                    );
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable) {
                    $blueprint->string($column, 255)->nullable($nullable)->change();
                });
            }
        }
    }

    /**
     * Tipo base della colonna (varchar, text, json…), null se non esiste.
     */
    private function dataType(string $table, string $column): ?string
    {
        $type = DB::selectOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $type?->DATA_TYPE;
    }
};
