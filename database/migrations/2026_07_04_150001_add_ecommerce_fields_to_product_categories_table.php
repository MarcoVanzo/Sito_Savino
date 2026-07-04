<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Aggiunta campi e-commerce alla tabella product_categories
|--------------------------------------------------------------------------
|
| Aggiunge supporto per:
| - parent_id: categorie annidate (self-referencing FK)
| - sort_order: ordinamento personalizzato
| - image: immagine della categoria
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            // Categoria padre per struttura ad albero
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('product_categories')
                  ->onDelete('set null');

            // Ordinamento personalizzato
            $table->integer('sort_order')->default(0)->after('description');

            // Immagine della categoria
            $table->string('image', 255)->nullable()->after('sort_order');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'sort_order', 'image']);
        });
    }
};
