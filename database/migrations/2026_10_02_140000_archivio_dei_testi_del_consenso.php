<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La prova del consenso deve dire anche QUALI informazioni sono state date
 * (EDPB 05/2020 §107-108), e una data e un'origine che non si possano
 * ritoccare (Garante, 25/09/2025).
 *
 * - `versioni_testi_consenso`: il testo davvero presentato al visitatore
 *   (banner, pannello, Cookie Policy, elenco dei cookie; modulo e conferma
 *   della newsletter), una riga per impronta. Il contenuto sta in testo e non
 *   in una colonna `json`: MySQL riordina e riscrive il JSON, e lo sha256 del
 *   contenuto conservato non tornerebbe più uguale all'impronta.
 * - `consensi_cookie`: l'impronta dei testi visti e la catena delle impronte
 *   (`impronta_precedente`, `impronta_riga`), che `consensi:verifica`
 *   ricalcola.
 * - `consensi_cookie_potature`: ogni giro di `consensi:pota` lascia l'impronta
 *   dell'ultima riga tolta. È l'ancora della catena: senza, togliere righe in
 *   testa al registro non si distinguerebbe dalla potatura per età.
 * - `newsletter_subscribers`: l'impronta del testo del modulo (alla richiesta)
 *   e di quello della conferma (al clic che rende valido il consenso).
 *
 * Idempotente: gira a ogni avvio in produzione.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('versioni_testi_consenso')) {
            Schema::create('versioni_testi_consenso', function (Blueprint $table) {
                $table->id();
                // `cookie` o `newsletter`.
                $table->string('tipo', 20)->index();
                $table->char('impronta', 64)->unique();
                $table->longText('contenuto');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('consensi_cookie', 'impronta_testi')) {
            Schema::table('consensi_cookie', function (Blueprint $table) {
                $table->char('impronta_testi', 64)->nullable()->after('versione');
                // Un testo a cui si riferisce un consenso non si può cancellare
                // nemmeno a mano dal database.
                $table->foreign('impronta_testi')
                    ->references('impronta')->on('versioni_testi_consenso')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('consensi_cookie', 'impronta_riga')) {
            Schema::table('consensi_cookie', function (Blueprint $table) {
                $table->char('impronta_precedente', 64)->nullable();
                $table->char('impronta_riga', 64)->nullable()->index();
            });
        }

        if (! Schema::hasTable('consensi_cookie_potature')) {
            Schema::create('consensi_cookie_potature', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fino_a_id');
                $table->unsignedInteger('righe');
                // Nullo se l'ultima riga tolta non era ancora sigillata.
                $table->char('ultima_impronta', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('newsletter_subscribers', 'impronta_testi_modulo')) {
            Schema::table('newsletter_subscribers', function (Blueprint $table) {
                $table->char('impronta_testi_modulo', 64)->nullable();
                $table->char('impronta_testi_conferma', 64)->nullable();
                $table->foreign('impronta_testi_modulo')
                    ->references('impronta')->on('versioni_testi_consenso')
                    ->restrictOnDelete();
                $table->foreign('impronta_testi_conferma')
                    ->references('impronta')->on('versioni_testi_consenso')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Niente: l'archivio dei testi e la catena sono la prova di che cosa diceva
     * il sito e di che cosa è stato scelto. Tornare indietro con il codice non
     * deve cancellarla.
     */
    public function down(): void
    {
        //
    }
};
