<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Allineamento precisione decimale (round 2)
|--------------------------------------------------------------------------
|
| La migrazione 2026_07_05_000004 ha allineato products.price a decimal(10,2).
| Rimangono disallineate:
| - order_items.price_at_time_of_purchase → decimal(8,2)
| - product_variants.price_modifier       → decimal(8,2)
|
| Allineamento a decimal(10,2) per consistenza con le altre colonne prezzo.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price_at_time_of_purchase', 10, 2)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price_modifier', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price_at_time_of_purchase', 8, 2)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price_modifier', 8, 2)->default(0)->change();
        });
    }
};
