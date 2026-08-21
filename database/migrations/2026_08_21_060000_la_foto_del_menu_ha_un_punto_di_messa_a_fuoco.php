<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dove inquadrare la foto nella tendina del menu.
 *
 * Il riquadro è più stretto della foto, quindi qualcosa si taglia sempre: la
 * foto del Ticketing restava decentrata perché il soggetto non sta al centro
 * dello scatto. Invece di scegliere una foto diversa al posto della redazione,
 * qui si sceglie quale parte tenere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('menu_image_position', 20)->default('center')->after('motto_subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('menu_image_position');
        });
    }
};
