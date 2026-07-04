<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Creazione tabella shipping_zones
|--------------------------------------------------------------------------
|
| Zone di spedizione con:
| - countries: array JSON di codici ISO 2 lettere dei paesi inclusi
| - flat_rate: tariffa fissa di spedizione
| - free_threshold: soglia di spesa per la spedizione gratuita
| - estimated_days_min / max: tempi di consegna stimati
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();

            // Nome della zona (es. "Italia", "Europa", "Mondo")
            $table->string('name', 100);

            // Paesi inclusi nella zona (array di codici ISO 2 lettere)
            $table->json('countries');

            // Tariffa fissa di spedizione
            $table->decimal('flat_rate', 10, 2)->default(0);

            // Soglia di spesa per la spedizione gratuita (null = mai gratuita)
            $table->decimal('free_threshold', 10, 2)->nullable();

            // Tempi di consegna stimati (in giorni)
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();

            // Stato di attivazione
            $table->boolean('is_active')->default(true);

            // Ordinamento personalizzato
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
