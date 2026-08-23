<?php

use App\Jobs\RitagliaILoghiDegliSponsor;
use Illuminate\Database\Migrations\Migration;

/**
 * Rimette in coda il ritaglio dei loghi degli sponsor.
 *
 * Il primo tentativo e' morto con "Call to undefined function
 * imagecreatefromstring": in produzione mancava l'estensione GD, e il job ha
 * esaurito i suoi tentativi prima che venisse installata. La migrazione
 * precedente e' gia' registrata e non riparte da sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mai in linea: con la coda `sync` — è così nei test — il dispatch
        // eseguirebbe il lavoro dentro la migrazione.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        RitagliaILoghiDegliSponsor::dispatch()->onConnection($coda);
    }

    public function down(): void
    {
        // Niente da annullare: questa migrazione non tocca lo schema, mette in
        // coda dei lavori. Quelli gia' eseguiti non si disfano tornando
        // indietro di una migrazione.
    }
};
