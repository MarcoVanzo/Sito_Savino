<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Aggiunta FK coupon_id alla tabella orders
|--------------------------------------------------------------------------
|
| Questa migrazione viene eseguita DOPO la creazione della tabella coupons
| (migrazione 150005) per aggiungere il vincolo di chiave esterna su
| coupon_id nella tabella orders.
|
| Il campo coupon_id è stato aggiunto nella migrazione 150002 senza FK,
| qui viene aggiunto solo il vincolo.
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Aggiunge il vincolo FK su coupon_id → coupons(id)
            $table->foreign('coupon_id')
                ->references('id')
                ->on('coupons')
                ->onDelete('set null');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
        });
    }
};
