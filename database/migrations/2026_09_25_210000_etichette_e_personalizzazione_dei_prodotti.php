<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Due richieste della redazione dello shop.
 *
 * Etichette in vetrina: `products.etichette` nullo significa "automatiche"
 * (NUOVO nei primi 30 giorni, IN OFFERTA durante lo sconto), com'era finora;
 * un elenco, anche vuoto, e' la scelta della redazione.
 *
 * Personalizzazione (di solito la firma della giocatrice): un'aggiunta
 * facoltativa al prodotto, con un supplemento. Non e' una variante: non ha
 * giacenza propria, e raddoppiare le taglie di ogni maglia avrebbe diviso a
 * meta' un magazzino che e' uno solo. Sta sulla riga del carrello e, fotografata
 * con nome e supplemento, su quella dell'ordine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('etichette')->nullable()->after('is_active');
            // Tradotta con spatie: text, mai varchar o json (CLAUDE.md §9).
            $table->text('personalizzazione_nome')->nullable()->after('etichette');
            $table->decimal('personalizzazione_prezzo', 8, 2)->default(0)->after('personalizzazione_nome');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->boolean('con_personalizzazione')->default(false)->after('product_variant_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->text('personalizzazione')->nullable()->after('product_variant_id');
            $table->decimal('supplemento_personalizzazione', 8, 2)->default(0)->after('personalizzazione');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['personalizzazione', 'supplemento_personalizzazione']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('con_personalizzazione');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['etichette', 'personalizzazione_nome', 'personalizzazione_prezzo']);
        });
    }
};
