<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'impianto ha cambiato denominazione: "Palazzo Wanny" diventa "PalaBigmat".
 * Cambia solo il nome, non la struttura né l'indirizzo.
 *
 * Le news pubblicate non vengono toccate: citano il nome in uso alla data
 * dell'articolo e riscriverle falsificherebbe l'archivio.
 */
return new class extends Migration
{
    private const FROM = 'Palazzo Wanny';

    private const TO = 'PalaBigmat';

    public function up(): void
    {
        $targets = [
            'pages' => ['title', 'content', 'excerpt', 'meta_description', 'content_data'],
            'site_settings' => ['value'],
            'games' => ['location'],
            'menu_items' => ['label'],
        ];

        foreach ($targets as $table => $columns) {
            foreach ($columns as $column) {
                // Solo le righe che contengono davvero il vecchio nome: alcune
                // di queste colonne sono di tipo JSON e riscriverle tutte con
                // COALESCE farebbe fallire MySQL sulle righe vuote (errore 3140).
                DB::table($table)
                    ->whereNotNull($column)
                    ->where($column, 'like', '%'.self::FROM.'%')
                    ->update([
                        $column => DB::raw(sprintf(
                            'REPLACE(`%s`, %s, %s)',
                            $column,
                            DB::getPdo()->quote(self::FROM),
                            DB::getPdo()->quote(self::TO)
                        )),
                    ]);
            }
        }
    }

    /**
     * Irreversibile per scelta: tornare indietro rimetterebbe online un nome
     * che l'impianto non ha più.
     */
    public function down(): void
    {
        // no-op
    }
};
