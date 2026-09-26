<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le condizioni di vendita vendono maglie da gara e articoli autografati
 * «nello stato descritto nella scheda»: la scheda deve quindi descriverlo.
 *
 * Nel catalogo non c'e' un criterio affidabile per riconoscerli: le maglie
 * indossate stanno in Asta, Outlet, Home, Away e Accessori, e "Maglia gara"
 * nel nome indica anche le repliche nuove in taglia. Il flag lo accende la
 * redazione; acceso, lo stato e' obbligatorio nel pannello.
 *
 * `order_items.stato_articolo` e' la fotografia per lingua al momento
 * dell'acquisto, come `personalizzazione`: la redazione puo' riscrivere la
 * scheda, l'ordine deve continuare a dire cosa e' stato venduto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('usato_o_autografato')->default(false)->after('personalizzazione_prezzo');
            // Tradotta con spatie: text, mai varchar o json (CLAUDE.md §9).
            $table->text('stato_articolo')->nullable()->after('usato_o_autografato');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->text('stato_articolo')->nullable()->after('supplemento_personalizzazione');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('stato_articolo');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['usato_o_autografato', 'stato_articolo']);
        });
    }
};
