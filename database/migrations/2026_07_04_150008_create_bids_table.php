<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Creazione tabella bids
|--------------------------------------------------------------------------
|
| Offerte per le aste con:
| - auction_id / user_id: chi ha offerto su quale asta
| - amount: importo dell'offerta
| - is_valid: flag per invalidazione (es. mancato pagamento vincitore)
| - placed_at: timestamp preciso del piazzamento
|
| Indice DESC su (auction_id, amount) per query efficienti
| sull'offerta più alta per asta.
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();

            // Riferimento all'asta
            $table->foreignId('auction_id')
                  ->constrained('auctions')
                  ->onDelete('cascade');

            // Utente che ha piazzato l'offerta
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Importo dell'offerta
            $table->decimal('amount', 10, 2);

            // Flag di validità (false se invalidata, es. vincitore non paga)
            $table->boolean('is_valid')->default(true);

            // Data di invalidazione (se applicabile)
            $table->timestamp('invalidated_at')->nullable();

            // Timestamp preciso del piazzamento dell'offerta
            $table->timestamp('placed_at');

            $table->timestamps();

            // Indice per ricerca offerte per utente su un'asta
            $table->index(['auction_id', 'user_id'], 'bids_auction_id_user_id_index');
        });

        // Indice DESC per query efficiente sull'offerta più alta per asta
        // Laravel non supporta nativamente indici DESC, usiamo SQL raw
        DB::statement('CREATE INDEX bids_auction_id_amount_desc_index ON bids (auction_id, amount DESC)');
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
