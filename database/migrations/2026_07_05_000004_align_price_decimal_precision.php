<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Align price decimal precision
|--------------------------------------------------------------------------
|
| La colonna products.price era decimal(8,2) dalla migrazione iniziale.
| Le colonne sale_price, total_price e simili usano decimal(10,2).
| Allineamento a decimal(10,2) per consistenza e per supportare
| importi fino a 99.999.999,99 €.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->change();
        });
    }
};
