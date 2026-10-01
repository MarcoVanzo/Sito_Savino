<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Mette in coda l'importazione della Gallery del vecchio sito.
 *
 * Non si fa qui dentro: sono settemila foto da scaricare e ricaricare, e
 * l'avvio del container non deve aspettarle. Il lavoro lo porta avanti il
 * worker, poche pagine per volta; alla fine toglie gli album costruiti per
 * sbaglio dalle copertine dei comunicati, cosi' la gallery non resta mai vuota.
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

    /**
     * Non reversibile: le foto importate restano. Toglierle non è quello che
     * si vuole quando si annulla una migrazione.
     */
    public function down(): void {}
};
