<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Aggiunta campi e-commerce alla tabella users
|--------------------------------------------------------------------------
|
| Aggiunge:
| - phone: numero di telefono per contatti e spedizioni
| - address: indirizzo strutturato in formato JSON
|   (via, civico, cap, città, provincia, paese)
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Numero di telefono
            $table->string('phone', 30)->nullable()->after('email');

            // Indirizzo strutturato in formato JSON
            $table->json('address')->nullable()->after('phone');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }
};
