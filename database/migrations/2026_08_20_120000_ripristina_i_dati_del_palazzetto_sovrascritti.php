<?php

use Illuminate\Database\Migrations\Migration;

/**
 * In produzione la pagina del palazzetto è tornata vuota tre minuti dopo il
 * rilascio che l'aveva sistemata: la migrazione ha riempito `content_data` alle
 * 11:02, il record risulta modificato alle 11:05 da un salvataggio del pannello
 * fatto con il modulo aperto prima del deploy, che ha riscritto sopra lo stato
 * vecchio (`{"services": []}`). Online sono spariti di nuovo nome dell'impianto,
 * indirizzo, mappa e servizi.
 *
 * Qui si ripete lo stesso riempimento. Non è una copia della logica: si richiama
 * la migrazione di allora, così le due non possono divergere. Riempie soltanto
 * le chiavi rimaste vuote, quindi non tocca nulla di quello che la redazione ha
 * scritto nel frattempo, e su un'installazione nuova non trova niente da fare.
 */
return new class extends Migration
{
    private const ORIGINALE = '2026_08_20_093000_ripristina_i_dati_delle_pagine_rimasti_vuoti.php';

    public function up(): void
    {
        $percorso = database_path('migrations/'.self::ORIGINALE);

        if (! is_file($percorso)) {
            return;
        }

        (require $percorso)->up();
    }

    public function down(): void
    {
        // no-op: come l'originale, togliere questi valori riporterebbe la pagina
        // allo stato in cui online non si vedeva nulla.
    }
};
