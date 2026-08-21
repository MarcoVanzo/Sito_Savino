<?php

namespace App\Jobs;

use App\Models\GalleryEvent;
use App\Services\GalleryLegacy\LettoreGalleryVecchioSito;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Importa la Gallery del vecchio sito un pezzo per volta.
 *
 * L'archivio sono 335 album e circa settemila foto: scaricarli tutti in una
 * volta non sta né dentro l'avvio del container né dentro il tempo massimo di
 * un job. Ogni esecuzione ne prende pochi e, se ne restano, rimette in coda se
 * stessa: il lavoro va avanti da solo e riprende da dove si era fermato anche
 * se il worker viene riavviato.
 */
class ImportaLaGalleryStorica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Album per esecuzione: circa centocinquanta foto, pochi minuti. */
    private const ALBUM_PER_VOLTA = 8;

    public int $timeout = 1800;

    public int $tries = 3;

    public function handle(LettoreGalleryVecchioSito $lettore): void
    {
        $opzioni = ['--limite' => self::ALBUM_PER_VOLTA];

        // Gli album costruiti dalle copertine dei comunicati si tolgono alla
        // fine, non all'inizio: sono quelli che la gallery mostra adesso, e
        // toglierli subito la lascerebbe vuota per tutte le ore che serve a
        // scaricare l'archivio.
        if (! $this->restanoAlbum($lettore)) {
            Artisan::call('gallery:importa-dal-vecchio-sito', ['--togli-quelli-dai-comunicati' => true, '--limite' => 0]);
            Log::info('Import gallery storica: finito', ['uscita' => Artisan::output()]);

            return;
        }

        Artisan::call('gallery:importa-dal-vecchio-sito', $opzioni);

        Log::info('Import gallery storica', ['uscita' => Artisan::output()]);

        // Stessa cautela della migrazione che l'ha avviato: con la coda `sync`
        // il rilancio diventerebbe una ricorsione dentro se stesso.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        self::dispatch()->onConnection($coda)->delay(now()->addMinute());
    }

    private function restanoAlbum(LettoreGalleryVecchioSito $lettore): bool
    {
        $fatti = GalleryEvent::query()
            ->whereNotNull('legacy_slug')
            ->has('galleryImages')
            ->pluck('legacy_slug')
            ->all();

        return array_diff($lettore->elencoAlbum(), $fatti) !== [];
    }
}
