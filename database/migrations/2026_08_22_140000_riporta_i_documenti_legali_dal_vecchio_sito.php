<?php

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
        // No-op dal 01/10/2026: il lavoro che metteva in coda leggeva il vecchio
        // sito WordPress, che dal passaggio del dominio non esiste piu', e il
        // codice e' stato tolto. In produzione la migrazione e' gia' applicata;
        // su un database nuovo non c'e' niente da importare.
    }

    public function down(): void
    {
        // Niente da annullare: la migrazione non tocca lo schema, mette in coda
        // un lavoro. Quello già eseguito non si disfa tornando indietro.
    }
};
