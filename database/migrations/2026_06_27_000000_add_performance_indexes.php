<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge indici di performance su colonne di stato e chiavi esterne
 * frequentemente usate nelle query.
 *
 * In PostgreSQL (usato su DigitalOcean) i vincoli FK non creano
 * automaticamente indici, a differenza di MySQL/MariaDB.
 * Questa migrazione garantisce performance ottimali su entrambi i DBMS.
 */
return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        // Indici sulla tabella posts
        Schema::table('posts', function (Blueprint $table) {
            $table->index('status', 'posts_status_index');
            $table->index('author_id', 'posts_author_id_index');
        });

        // Indici sulla tabella pages (slug ha già un indice unique)
        Schema::table('pages', function (Blueprint $table) {
            $table->index('status', 'pages_status_index');
            $table->index('author_id', 'pages_author_id_index');
        });

        // Indici sulla tabella orders
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_index');
            $table->index('user_id', 'orders_user_id_index');
        });

        // Indici sulla tabella games
        Schema::table('games', function (Blueprint $table) {
            $table->index('status', 'games_status_index');
            $table->index('season_id', 'games_season_id_index');
        });

        // Indice composito sulla tabella rosters (usato per filtrare per squadra e stagione)
        Schema::table('rosters', function (Blueprint $table) {
            $table->index(['team_id', 'season_id'], 'rosters_team_id_season_id_index');
        });

        // Indice sulla tabella player_stats (player_id è nel composite unique, ma serve anche da solo)
        Schema::table('player_stats', function (Blueprint $table) {
            $table->index('player_id', 'player_stats_player_id_index');
        });

        // Indici sulla tabella stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('product_id', 'stock_movements_product_id_index');
            $table->index('product_variant_id', 'stock_movements_product_variant_id_index');
            $table->index('type', 'stock_movements_type_index');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_status_index');
            $table->dropIndex('posts_author_id_index');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_status_index');
            $table->dropIndex('pages_author_id_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_user_id_index');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex('games_status_index');
            $table->dropIndex('games_season_id_index');
        });

        Schema::table('rosters', function (Blueprint $table) {
            $table->dropIndex('rosters_team_id_season_id_index');
        });

        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropIndex('player_stats_player_id_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_product_id_index');
            $table->dropIndex('stock_movements_product_variant_id_index');
            $table->dropIndex('stock_movements_type_index');
        });
    }
};
