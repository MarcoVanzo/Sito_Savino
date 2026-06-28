<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge indici di performance su colonne di stato e chiavi esterne
 * frequentemente usate nelle query.
 *
 * MySQL 8.4 (usato su DigitalOcean) crea indici automatici per le FK,
 * ma non per le colonne usate solo in WHERE/ORDER BY.
 * Questa migrazione aggiunge indici espliciti per query ottimali.
 */
return new class extends Migration
{
    /**
     * Mappa tabella => lista di colonne (o array di colonne per indici compositi)
     * da indicizzare. Il nome dell'indice è generato automaticamente.
     */
    private function indexDefinitions(): array
    {
        return [
            'posts' => ['status', 'author_id'],
            'pages' => ['status', 'author_id'],
            'orders' => ['status', 'user_id'],
            'games' => ['status', 'season_id'],
            'rosters' => [['team_id', 'season_id']],
            'player_stats' => ['player_id'],
            'stock_movements' => ['product_id', 'product_variant_id', 'type'],
        ];
    }

    /**
     * Genera il nome dell'indice da tabella e colonne.
     */
    private function indexName(string $table, string|array $columns): string
    {
        $suffix = is_array($columns) ? implode('_', $columns) : $columns;

        return "{$table}_{$suffix}_index";
    }

    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        foreach ($this->indexDefinitions() as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column, $this->indexName($table, $column));
                }
            });
        }
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        foreach ($this->indexDefinitions() as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex($this->indexName($table, $column));
                }
            });
        }
    }
};
