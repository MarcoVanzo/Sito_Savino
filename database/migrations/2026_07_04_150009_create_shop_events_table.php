<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Creazione tabella shop_events
|--------------------------------------------------------------------------
|
| Tabella di analytics/tracking per eventi dello shop:
| - Visualizzazioni prodotti, categorie
| - Aggiunte al carrello
| - Inizio checkout, completamento ordine
|
| Relazione polimorfica (viewable_type + viewable_id) per flessibilità.
| Nessuna FK su user_id per performance (scritture ad alto volume).
| Solo created_at, senza updated_at (eventi immutabili).
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::create('shop_events', function (Blueprint $table) {
            $table->id();

            // Tipo di evento (product_view, add_to_cart, checkout_start, ecc.)
            $table->string('event_type', 30);

            // Relazione polimorfica all'entità visualizzata/interagita
            $table->string('viewable_type', 255)->nullable();
            $table->unsignedBigInteger('viewable_id')->nullable();

            // Utente (senza FK per performance su tabella ad alto volume)
            $table->unsignedBigInteger('user_id')->nullable();

            // Identificazione sessione
            $table->string('session_id', 100)->nullable();

            // Indirizzo IP (IPv4 e IPv6)
            $table->string('ip_address', 45)->nullable();

            // Metadati aggiuntivi in formato JSON
            $table->json('metadata')->nullable();

            // Solo created_at — gli eventi sono immutabili
            $table->timestamp('created_at');

            // Indici per query analitiche
            $table->index(['event_type', 'created_at'], 'shop_events_event_type_created_at_index');
            $table->index(['viewable_type', 'viewable_id', 'created_at'], 'shop_events_viewable_created_at_index');
            $table->index('created_at', 'shop_events_created_at_index');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_events');
    }
};
