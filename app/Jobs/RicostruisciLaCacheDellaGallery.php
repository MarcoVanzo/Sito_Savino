<?php

namespace App\Jobs;

use App\Services\GalleryArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Rigenera in coda la copia in cache dell'archivio fotografico.
 *
 * Parte a ogni foto, album o atleta salvati e ogni ora dallo scheduler.
 * Unico finché non entra in lavorazione: un import di cento foto ne mette in
 * coda uno solo, ma una modifica arrivata mentre gira ne fa partire un altro
 * dopo, così l'ultima foto non resta fuori.
 */
class RicostruisciLaCacheDellaGallery implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Sotto il `retry_after` della coda (180 s): un job che sfora non deve
     * essere ripreso da un secondo worker mentre il primo sta ancora
     * scrivendo la cache. La ricostruzione vera dura una decina di secondi.
     */
    public int $timeout = 150;

    public int $tries = 2;

    /**
     * Il lock di unicità scade da solo: un job perso prima della lavorazione
     * (coda svuotata, payload scartato) non deve bloccare per un giorno
     * quelli successivi, compreso il giro orario dello scheduler.
     */
    public int $uniqueFor = 900;

    public function handle(GalleryArchive $archivio): void
    {
        $archivio->riscalda();
    }
}
