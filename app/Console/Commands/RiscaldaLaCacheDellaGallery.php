<?php

namespace App\Console\Commands;

use App\Jobs\RicostruisciLaCacheDellaGallery;
use Illuminate\Console\Command;

/**
 * Mette in coda la ricostruzione della cache della gallery.
 *
 * `start.sh` svuota la cache a ogni avvio del container: senza questo passo
 * il primo visitatore della gallery dopo un deploy aspettava i dieci secondi
 * della ricostruzione. Il job è unico finché non entra in lavorazione, quindi
 * lanciarlo più volte non accoda doppioni.
 */
class RiscaldaLaCacheDellaGallery extends Command
{
    protected $signature = 'gallery:riscalda-cache';

    protected $description = 'Mette in coda la ricostruzione della cache dell\'archivio fotografico';

    public function handle(): int
    {
        RicostruisciLaCacheDellaGallery::dispatch();

        $this->info('Ricostruzione della cache della gallery messa in coda.');

        return self::SUCCESS;
    }
}
