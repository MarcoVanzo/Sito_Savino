<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le dichiarazioni di recesso inviate con la funzione online.
 *
 * L'art. 54-bis del Codice del Consumo (d.lgs. 209/2025, in vigore dal
 * 19/06/2026) chiede che il consumatore possa recedere dal sito con la stessa
 * facilita' con cui ha comprato, e che riceva subito una ricevuta con il
 * contenuto della dichiarazione, la data e l'ora. La riga e' quella prova:
 * `inviata_il` e' l'istante della conferma, non quello di creazione.
 *
 * `order_id` e' valorizzato quando il numero d'ordine corrisponde a un ordine:
 * la dichiarazione si accetta comunque, perche' il diritto non dipende dal
 * fatto che il cliente ricordi il numero giusto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('richieste_di_recesso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('numero_ordine', 50);
            $table->string('nome', 255);
            $table->string('email', 255);
            $table->text('articoli')->nullable();
            $table->string('lingua', 5)->default('it');
            $table->timestamp('inviata_il');
            $table->timestamp('gestita_il')->nullable();
            $table->text('note_interne')->nullable();
            $table->timestamps();

            $table->index('inviata_il');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('richieste_di_recesso');
    }
};
