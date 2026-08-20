<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggancio stabile fra atleta e pagina Wikipedia.
 *
 * Senza il titolo salvato ogni importazione ripartirebbe dalla ricerca per
 * nome, che non è deterministica: "Julia Bergmann" sta su "Júlia Bergmann" e
 * un omonimo può scalzare la voce giusta. Salvato una volta (o corretto a mano
 * in redazione), l'aggancio non si rimette più in discussione.
 *
 * `wikipedia_revid` è la revisione da cui è stato letto il palmarès: permette
 * di saltare le atlete la cui pagina non è cambiata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('wikipedia_title')->nullable()->after('lega_volley_id');
            $table->string('wikipedia_lang', 5)->nullable()->after('wikipedia_title');
            $table->unsignedBigInteger('wikipedia_revid')->nullable()->after('wikipedia_lang');
            $table->timestamp('palmares_synced_at')->nullable()->after('wikipedia_revid');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['wikipedia_title', 'wikipedia_lang', 'wikipedia_revid', 'palmares_synced_at']);
        });
    }
};
