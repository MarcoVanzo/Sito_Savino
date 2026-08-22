<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Rigenera le anteprime che non sono mai state prodotte.
 *
 * Fino al 21 agosto in produzione mancava l'estensione GD: ogni conversione
 * moriva con "Call to undefined function imagecreatefromstring" e i job hanno
 * esaurito i tentativi. Adesso GD c'è e le immagini nuove hanno le loro
 * anteprime, ma le tremila già caricate no: la libreria ripiega
 * sull'originale, quindi non si vede rotto — si vede lento, ed è esattamente
 * il difetto che l'installazione di GD doveva chiudere.
 *
 * Si lavora a blocchi rimettendo in coda se stesso, come l'import della
 * gallery: tremila conversioni non stanno nel tempo massimo di un job, e così
 * il lavoro riprende da dov'era anche se il worker viene riavviato.
 *
 * Il blocco successivo si sceglie per identificativo decrescente, non
 * ripescando ogni volta "quelli senza anteprima": un file che non si riesce a
 * convertire — un PDF caricato al posto di una foto, un originale sparito da
 * Spaces — resta senza anteprima e la coda gli passa oltre, invece di
 * ritentarlo all'infinito.
 */
class RigeneraLeAnteprimeMancanti implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** File per esecuzione: ognuno costa due o tre ridimensionamenti. */
    private const PER_VOLTA = 50;

    public int $timeout = 1800;

    public int $tries = 3;

    /**
     * @param  int|null  $primaDi  Riparte dai file con identificativo minore di
     *                             questo; null alla prima esecuzione.
     */
    public function __construct(private readonly ?int $primaDi = null) {}

    public function handle(): void
    {
        $ids = $this->prossimoBlocco();

        if ($ids === []) {
            Log::info('Rigenerazione anteprime: finita');

            return;
        }

        Artisan::call('media-library:regenerate', [
            '--ids' => array_map('strval', $ids),
            '--only-missing' => true,
            '--force' => true,
        ]);

        Log::info('Rigenerazione anteprime', [
            'blocco' => count($ids),
            'restano' => $this->senzaAnteprime()->where('id', '<', min($ids))->count(),
        ]);

        // Stessa cautela dell'import della gallery: con la coda `sync` — è così
        // nei test — il rilancio diventerebbe una ricorsione dentro se stesso.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        self::dispatch(min($ids))->onConnection($coda)->delay(now()->addSeconds(10));
    }

    /**
     * @return array<int, int>
     */
    private function prossimoBlocco(): array
    {
        $query = $this->senzaAnteprime();

        if ($this->primaDi !== null) {
            $query->where('id', '<', $this->primaDi);
        }

        return $query->orderByDesc('id')->limit(self::PER_VOLTA)->pluck('id')->all();
    }

    /**
     * I file che non hanno mai prodotto un'anteprima.
     *
     * @return Builder<Media>
     */
    private function senzaAnteprime()
    {
        // `generated_conversions` è una colonna JSON: confrontarla con la
        // stringa '[]' non trova niente, perché MySQL mette a confronto due
        // tipi diversi. Si guarda quanti elementi contiene.
        return Media::query()->where(
            fn ($q) => $q->whereNull('generated_conversions')
                ->orWhereRaw('JSON_LENGTH(generated_conversions) = 0')
        );
    }
}
