<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Palmarès delle atlete: una riga per trofeo, medaglia o premio individuale.
 *
 * Non si tiene una riga per competizione con la lista degli anni dentro: così
 * l'ordinamento cronologico e il conteggio dei trofei sono SQL e non stringhe
 * da spacchettare. È la pagina pubblica ad aggregare ("2× Coppa CEV").
 *
 * `competition` e `note` sono `text` perché traducibili (JSON per lingua):
 * vedi CLAUDE.md §9, con `varchar` si sfondano i 255 caratteri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_honours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            // club | national | individual (App\Enums\PlayerHonourCategory)
            $table->string('category', 20);

            $table->text('competition');

            // Edizione come la scrive la fonte: "2020-21", "Rio de Janeiro 2016".
            $table->string('edition', 160)->nullable();

            // Anno di riferimento, estratto dall'edizione: serve solo a ordinare.
            $table->smallInteger('year')->unsigned()->nullable();

            // gold | silver | bronze. Null = titolo vinto (i trofei di club non
            // hanno un piazzamento: o si alza la coppa o non si entra in elenco).
            $table->string('medal', 10)->nullable();

            $table->text('note')->nullable();

            // wikipedia | manual. Il sync riscrive SOLO le righe `wikipedia`:
            // quello che la redazione tocca diventa `manual` e non si perde più.
            $table->string('source', 20)->default('manual');

            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['player_id', 'category', 'year']);
            $table->index(['player_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_honours');
    }
};
