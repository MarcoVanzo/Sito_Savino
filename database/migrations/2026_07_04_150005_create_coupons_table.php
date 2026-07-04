<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Creazione tabella coupons
|--------------------------------------------------------------------------
|
| Coupon/codici sconto con:
| - code: codice univoco inserito dall'utente
| - type: tipo di sconto (percentage, fixed)
| - value: valore dello sconto (% o importo fisso)
| - max_discount: tetto massimo per sconti percentuali
| - min_order_amount: importo minimo ordine per l'utilizzo
| - max_uses / max_uses_per_user: limiti di utilizzo
| - valid_from / valid_until: finestra di validità
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Codice univoco del coupon
            $table->string('code', 50)->unique();

            // Tipo di sconto: 'percentage' o 'fixed'
            $table->string('type', 20)->default('percentage');

            // Valore dello sconto (percentuale o importo fisso)
            $table->decimal('value', 10, 2);

            // Tetto massimo di sconto per coupon percentuali
            $table->decimal('max_discount', 10, 2)->nullable();

            // Importo minimo ordine per poter applicare il coupon
            $table->decimal('min_order_amount', 10, 2)->nullable();

            // Limiti di utilizzo globali e per singolo utente
            $table->integer('max_uses')->nullable();
            $table->integer('max_uses_per_user')->default(1);
            $table->integer('used_count')->default(0);

            // Finestra di validità
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            // Stato di attivazione
            $table->boolean('is_active')->default(true);

            // Descrizione interna (per uso admin)
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
