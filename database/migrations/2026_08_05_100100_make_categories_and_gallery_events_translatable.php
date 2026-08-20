<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rende traducibili i nomi delle categorie news e i titoli degli album di gallery.
 *
 * Sono gli ultimi contenuti visibili al pubblico che non avevano nemmeno il
 * campo inglese: sul sito in inglese i filtri delle news mostravano "Notizie",
 * "Società", "Giovanile" e gli album "Giugno 2026 — News".
 *
 * Le colonne diventano `text` e non `json`, come da regola di progetto: una
 * riga legacy rimasta in testo semplice farebbe fallire l'ALTER e bloccherebbe
 * l'avvio del container, dato che le migrazioni girano a ogni deploy.
 *
 * `down()` riporta il tipo di colonna ma non ricostruisce il testo semplice: i
 * valori restano JSON per lingua, altrimenti si perderebbe la traduzione
 * inglese senza modo di recuperarla.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'categories' => ['name'],
        'gallery_events' => ['title'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->text($column)->nullable()->change();
                }
            });

            $this->wrapExistingValues($table, $columns);
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->string($column, 255)->nullable()->change();
                }
            });
        }
    }

    /**
     * Trasforma il testo semplice nel JSON per lingua atteso da spatie,
     * lasciando intatte le righe già convertite (la migrazione è rieseguibile).
     *
     * @param  list<string>  $columns
     */
    private function wrapExistingValues(string $table, array $columns): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns) {
            foreach ($rows as $row) {
                $update = [];

                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $decoded = json_decode((string) $value, true);

                    if (is_array($decoded) && (isset($decoded['it']) || isset($decoded['en']))) {
                        continue;
                    }

                    $update[$column] = json_encode(['it' => (string) $value], JSON_UNESCAPED_UNICODE);
                }

                if ($update !== []) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            }
        });
    }
};
