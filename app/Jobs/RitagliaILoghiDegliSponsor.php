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
 * Toglie il margine bianco ai loghi degli sponsor già in archivio.
 *
 * Sono settantasei immagini da scaricare, ritagliare e ricaricare su Spaces:
 * non è lavoro da fare dentro l'avvio del container, che aspetterebbe.
 */
class RitagliaILoghiDegliSponsor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public function handle(): void
    {
        Artisan::call('sponsor:ritaglia-loghi');

        Log::info('Ritaglio loghi sponsor', ['uscita' => Artisan::output()]);
    }
}
