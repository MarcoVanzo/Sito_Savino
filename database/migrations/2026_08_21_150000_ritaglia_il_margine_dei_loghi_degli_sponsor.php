<?php

use App\Jobs\RitagliaILoghiDegliSponsor;
use Illuminate\Database\Migrations\Migration;

/**
 * Mette in coda il ritaglio dei loghi degli sponsor.
 *
 * Sul vecchio sito i marchi stavano dentro riquadri 600x400 con il marchio
 * vero alto un terzo e il resto bianco. L'importazione li ha ripresi cosi', e
 * nella pagina Sponsor il logo sembra minuscolo dentro una card grande. Non e'
 * impaginazione: e' margine cotto dentro il file.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mai in linea: con la coda `sync` — è così nei test — il dispatch
        // eseguirebbe il lavoro dentro la migrazione, e la migrazione gira
        // all'avvio di ogni container.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        RitagliaILoghiDegliSponsor::dispatch()->onConnection($coda);
    }

    /**
     * Non reversibile: il margine tolto non si rimette.
     */
    public function down(): void {}
};
