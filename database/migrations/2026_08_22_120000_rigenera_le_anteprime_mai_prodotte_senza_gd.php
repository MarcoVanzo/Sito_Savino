<?php

use App\Jobs\RigeneraLeAnteprimeMancanti;
use Illuminate\Database\Migrations\Migration;

/**
 * Avvia la rigenerazione delle anteprime perse nei mesi senza GD.
 *
 * Installare l'estensione ha sistemato le immagini caricate da lì in avanti —
 * le ottomila di venerdì hanno tutte le loro conversioni — ma non le tremila
 * precedenti: quei job erano già finiti in `failed_jobs` e nessuno li rilancia.
 * Fino ad allora la gallery serve gli originali a piena risoluzione.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mai in linea: con la coda `sync` — è così nei test — il dispatch
        // eseguirebbe tutto il lavoro dentro la migrazione.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        RigeneraLeAnteprimeMancanti::dispatch()->onConnection($coda);
    }

    public function down(): void {}
};
