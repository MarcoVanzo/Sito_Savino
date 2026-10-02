<?php

use App\Services\CatenaDeiConsensi;
use Illuminate\Database\Migrations\Migration;

/**
 * Sigilla nella catena delle impronte, in ordine di id, i consensi registrati
 * prima che la catena esistesse (dal 22/09/2026).
 *
 * Non prova che quelle righe non siano state toccate prima di oggi: prova che
 * da oggi non lo sono. L'impronta è un HMAC con `CONSENSI_SALE` (ripiego su
 * `APP_KEY`, CatenaDeiConsensi::impronta): il segreto va impostato prima del
 * deploy che porta questa migrazione, o la catena nasce legata ad `APP_KEY` e
 * la rotazione della chiave la farebbe risultare alterata. Le righe senza `impronta_testi` sono quelle raccolte
 * prima dell'archivio dei testi: per loro vale `versione`.
 *
 * Idempotente: tocca solo le righe ancora senza impronta, e a ogni avvio non
 * ne trova.
 */
return new class extends Migration
{
    public function up(): void
    {
        CatenaDeiConsensi::sigillaIMancanti();
    }

    /**
     * Niente: togliere le impronte vorrebbe dire togliere la prova.
     */
    public function down(): void
    {
        //
    }
};
