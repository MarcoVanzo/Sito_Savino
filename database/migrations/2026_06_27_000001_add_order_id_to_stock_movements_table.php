<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge FK order_id a stock_movements per idempotenza affidabile.
     * Sostituisce il fragile check LIKE su notes che causava false-positive
     * (Ordine #1 matchava anche #10, #100, ecc.).
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained()
                ->nullOnDelete();

            // Indice per query di idempotenza: WHERE order_id = ? AND type = ?
            $table->index(['order_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
