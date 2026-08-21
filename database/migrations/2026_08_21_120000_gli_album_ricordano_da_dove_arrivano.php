<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'album importato dal vecchio sito si ricorda da quale pagina viene.
 *
 * Senza, rilanciare l'importazione duplicherebbe tutto: il titolo non basta
 * come chiave perché la redazione può cambiarlo, ed è una colonna tradotta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_events', function (Blueprint $table) {
            $table->string('legacy_slug')->nullable()->unique()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_events', function (Blueprint $table) {
            $table->dropUnique(['legacy_slug']);
            $table->dropColumn('legacy_slug');
        });
    }
};
