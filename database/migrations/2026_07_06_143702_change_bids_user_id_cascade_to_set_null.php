<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Cambio FK bids.user_id da CASCADE a SET NULL
|--------------------------------------------------------------------------
|
| Quando un utente viene eliminato, le offerte nelle aste devono
| rimanere come storico (con user_id = NULL) anziché essere cancellate
| a cascata. Questo preserva l'integrità dello storico aste.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            // Rendi user_id nullable (se non lo è già)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Rimuovi la FK esistente con CASCADE
            $table->dropForeign(['user_id']);

            // Riaggiungi con SET NULL on delete
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            // Rimuovi la FK con SET NULL
            $table->dropForeign(['user_id']);

            // Ripristina user_id come non nullable
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            // Riaggiungi la FK originale con CASCADE
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
};
