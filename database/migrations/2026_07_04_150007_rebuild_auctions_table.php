<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Ricostruzione tabella auctions
|--------------------------------------------------------------------------
|
| La tabella auctions esistente è vuota (solo id + timestamps).
| Viene eliminata e ricreata con la struttura completa per le aste:
| - Collegamento al prodotto (1:1)
| - Prezzi: partenza, offerta corrente, riserva, incremento, salto massimo
| - Date di inizio/fine asta
| - Gestione vincitore con tentativo, token checkout e scadenza
| - Supporto aste benefiche
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        // Elimina la tabella vuota esistente
        Schema::dropIfExists('auctions');

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();

            // Prodotto all'asta (relazione 1:1)
            $table->foreignId('product_id')
                  ->unique()
                  ->constrained('products')
                  ->onDelete('cascade');

            // Titolo e descrizione dell'asta
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Prezzi dell'asta
            $table->decimal('starting_price', 10, 2);
            $table->decimal('current_bid', 10, 2)->nullable();
            $table->decimal('reserve_price', 10, 2)->nullable();
            $table->decimal('bid_increment', 10, 2)->default(5.00);
            $table->decimal('max_bid_jump', 10, 2)->default(300.00);

            // Periodo dell'asta
            $table->timestamp('start_date');
            $table->timestamp('end_date');

            // Stato dell'asta (draft, active, ended, cancelled)
            $table->string('status', 20)->default('draft');

            // Vincitore dell'asta
            $table->foreignId('winner_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // Tentativo corrente del vincitore (per gestione mancato pagamento)
            $table->tinyInteger('current_winner_attempt')->unsigned()->default(1);

            // Token e scadenza per il checkout del vincitore
            $table->char('winner_checkout_token', 36)->nullable()->unique();
            $table->timestamp('winner_checkout_deadline')->nullable();

            // Asta benefica
            $table->boolean('is_charity')->default(false);
            $table->text('charity_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indici per query frequenti
            $table->index(['status', 'end_date'], 'auctions_status_end_date_index');
            $table->index(['status', 'start_date'], 'auctions_status_start_date_index');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');

        // Ricrea la tabella vuota originale
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
