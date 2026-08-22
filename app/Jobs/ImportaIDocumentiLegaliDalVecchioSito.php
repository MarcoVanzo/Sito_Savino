<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Riporta i PDF di Corporate Governance dal sito precedente.
 *
 * Cinque file da scaricare e ricaricare su Spaces: poco, ma sta comunque
 * dietro a due siti esterni e non è lavoro da fare dentro l'avvio del
 * container, che aspetterebbe.
 */
class ImportaIDocumentiLegaliDalVecchioSito implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function handle(): void
    {
        Artisan::call('documenti:importa-dal-vecchio-sito');

        Log::info('Import documenti legali', ['uscita' => Artisan::output()]);
    }
}
