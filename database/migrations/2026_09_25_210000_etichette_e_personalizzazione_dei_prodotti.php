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
        // Ogni colonna ha la sua guardia: su MySQL il DDL non e' transazionale,
        // e una migrazione interrotta a meta' lascia le prime colonne create e
        // la riga di `migrations` mancante. Senza guardie la ripartenza si
        // ferma su "Duplicate column". In produzione e' gia' applicata: qui
        // cambia solo cosa succede a un secondo giro.
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'etichette')) {
                $table->json('etichette')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('products', 'personalizzazione_nome')) {
                // Tradotta con spatie: text, mai varchar o json (CLAUDE.md §9).
                $table->text('personalizzazione_nome')->nullable()->after('etichette');
            }
            if (! Schema::hasColumn('products', 'personalizzazione_prezzo')) {
                $table->decimal('personalizzazione_prezzo', 8, 2)->default(0)->after('personalizzazione_nome');
            }
        });

        if (! Schema::hasColumn('cart_items', 'con_personalizzazione')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->boolean('con_personalizzazione')->default(false)->after('product_variant_id');
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'personalizzazione')) {
                $table->text('personalizzazione')->nullable()->after('product_variant_id');
            }
            if (! Schema::hasColumn('order_items', 'supplemento_personalizzazione')) {
                $table->decimal('supplemento_personalizzazione', 8, 2)->default(0)->after('personalizzazione');
            }
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
