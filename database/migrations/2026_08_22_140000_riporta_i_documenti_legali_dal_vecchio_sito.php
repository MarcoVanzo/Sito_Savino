<?php

use App\Jobs\ImportaIDocumentiLegaliDalVecchioSito;
use Illuminate\Database\Migrations\Migration;

/**
 * Avvia il recupero dei documenti di Corporate Governance.
 *
 * Le impostazioni `legal.*` sono vuote da quando i file sono spariti col
 * passaggio a Spaces: le voci di footer che li chiedono si nascondono da sole,
 * e l'informativa fornitori ripiega su `/informativa-fornitori`, che non è una
 * rotta e dà 404. I documenti sono ancora pubblicati sul sito precedente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mai in linea: con la coda `sync` — è così nei test — il dispatch
        // eseguirebbe il lavoro dentro la migrazione, aspettando due siti
        // esterni all'avvio del container.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        ImportaIDocumentiLegaliDalVecchioSito::dispatch()->onConnection($coda);
    }

    public function down(): void
    {
        // Niente da annullare: la migrazione non tocca lo schema, mette in coda
        // un lavoro. Quello già eseguito non si disfa tornando indietro.
    }
};
