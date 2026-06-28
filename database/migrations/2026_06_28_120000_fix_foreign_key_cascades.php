<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambia cascadeOnDelete → restrictOnDelete su FK critiche
     * per proteggere audit trail e dati storici.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->restrictOnDelete();
        });

        Schema::table('rosters', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
            $table->foreign('player_id')
                ->references('id')->on('players')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->cascadeOnDelete();
        });

        Schema::table('rosters', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
            $table->foreign('player_id')
                ->references('id')->on('players')
                ->cascadeOnDelete();
        });
    }
};
