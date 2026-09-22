<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il registro delle scelte fatte sul banner dei cookie.
 *
 * Il GDPR chiede di poter dimostrare il consenso (art. 7 §1), e finora la
 * scelta viveva solo nel `localStorage` del visitatore: cancellata la cronologia
 * del browser, della prova non restava niente. È il servizio che i gestori del
 * consenso a pagamento chiamano "consent log", e qui costa una tabella.
 *
 * Cosa NON si scrive, di proposito: l'indirizzo IP in chiaro. Ne resta
 * l'impronta con un sale, che basta a distinguere due visitatori e a rispondere
 * a "questo consenso da dove arriva", e non è un dato che si possa rileggere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consensi_cookie', function (Blueprint $table) {
            $table->id();

            // L'identificativo che il visitatore vede nelle impostazioni e che
            // cita se scrive per chiedere conto del proprio consenso. Non è
            // unico: chi cambia idea tiene il suo riferimento e aggiunge una
            // riga, così la storia delle sue scelte si legge in fila.
            $table->uuid('riferimento')->index();

            $table->boolean('statistiche')->default(false);
            $table->boolean('marketing')->default(false);

            // `concesso` alla prima scelta, `aggiornato` a ogni modifica,
            // `revocato` quando si tolgono tutte le voci facoltative.
            $table->string('azione', 20)->default('concesso');

            // La versione dei testi a cui il visitatore ha detto sì: senza,
            // un consenso raccolto su un'informativa diversa non si distingue.
            $table->string('versione', 20);

            $table->string('locale', 5)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('impronta_ip', 64)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Per la potatura dei consensi scaduti e per leggere l'ultimo
            // consenso di un visitatore che torna.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consensi_cookie');
    }
};
