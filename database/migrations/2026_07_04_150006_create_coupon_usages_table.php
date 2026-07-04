<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Creazione tabella coupon_usages
|--------------------------------------------------------------------------
|
| Traccia l'utilizzo dei coupon per ogni ordine.
| Supporta sia utenti registrati che ospiti.
| Vincolo univoco (coupon_id, order_id) per evitare doppi utilizzi.
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();

            // Riferimento al coupon utilizzato
            $table->foreignId('coupon_id')
                  ->constrained('coupons')
                  ->onDelete('cascade');

            // Riferimento all'ordine in cui è stato usato
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->onDelete('cascade');

            // Utente che ha usato il coupon (null se ospite)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // Email ospite (se non registrato)
            $table->string('guest_email', 255)->nullable();

            // Data e ora di utilizzo
            $table->timestamp('used_at');

            $table->timestamps();

            // Un coupon può essere usato una sola volta per ordine
            $table->unique(['coupon_id', 'order_id']);
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
