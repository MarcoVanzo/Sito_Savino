<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara le notizie alla traduzione automatica in inglese (DeepL).
 *
 * - `posts.title` era rimasta `varchar(255)`: con italiano e inglese nello
 *   stesso JSON (e le lettere accentate scritte come `à`) un titolo lungo
 *   sfora e MySQL risponde 1406. Diventa `text`, come le altre colonne
 *   tradotte.
 * - `posts.traduzioni_automatiche` ricorda, campo per campo, l'impronta
 *   dell'italiano tradotto e dell'inglese prodotto: serve a capire se
 *   l'inglese l'ha poi corretto la redazione, e in quel caso non si tocca più.
 *
 * `down()` toglie la colonna nuova ma lascia `title` in `text`: riportarla a
 * 255 caratteri troncherebbe i titoli che hanno già la traduzione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('title')->change();
        });

        if (! Schema::hasColumn('posts', 'traduzioni_automatiche')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->json('traduzioni_automatiche')->nullable()->after('meta_description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'traduzioni_automatiche')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('traduzioni_automatiche');
            });
        }
    }
};
