<?php

use App\Jobs\ImportaLaGalleryStorica;
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
        // Mai in linea: con la coda `sync` — è così nei test — il dispatch
        // eseguirebbe l'importazione dentro la migrazione, e la migrazione gira
        // all'avvio di ogni container. Il lavoro deve finire in coda e basta.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        ImportaLaGalleryStorica::dispatch()->onConnection($coda);
    }

    /**
     * Non reversibile: le foto importate restano. Toglierle non è quello che
     * si vuole quando si annulla una migrazione.
     */
    public function down(): void {}
};
