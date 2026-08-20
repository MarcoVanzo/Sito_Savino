<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link della diretta streaming della singola gara, inserito dalla redazione.
 *
 * Non arriva dalla Lega: il sync riempie solo le colonne `lvf_*`, quindi il
 * valore inserito a mano resta anche dopo una risincronizzazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('stream_url', 500)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('stream_url');
        });
    }
};
