<?php

namespace App\Console\Commands;

use App\Models\Sponsor;
use App\Support\RitaglioDelMargine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Toglie il margine bianco attorno ai loghi degli sponsor già in archivio.
 *
 * Sul vecchio sito i marchi stavano dentro riquadri 600x400, con il marchio
 * vero alto un terzo e il resto bianco. L'importazione li ha ripresi così, e
 * nella pagina Sponsor il logo sembra minuscolo dentro una card grande: non è
 * un problema di impaginazione, è margine cotto dentro il file.
 *
 * È ripetibile: al secondo passaggio non c'è più margine da togliere e non
 * tocca niente. Le immagini che arrivano fino al bordo, o che hanno un fondo
 * non uniforme, restano come sono.
 */
class RitagliaILoghiDegliSponsor extends Command
{
    protected $signature = 'sponsor:ritaglia-loghi {--prova : elenca soltanto, senza riscrivere}';

    protected $description = 'Toglie il margine uniforme attorno ai loghi degli sponsor';

    public function handle(): int
    {
        // Le versioni ridotte si generano qui, non in coda: finche' non
        // esistono la pagina mostra un riquadro vuoto al posto del logo, e
        // sostituendone settantasei in una volta la coda ci mette parecchio.
        config(['media-library.queue_conversions_by_default' => false]);

        $sponsor = Sponsor::with('media')->get();

        $ritagliati = 0;
        $lasciati = 0;

        foreach ($sponsor as $uno) {
            $logo = $uno->getFirstMedia('sponsors');

            if (! $logo) {
                continue;
            }

            $esito = $this->ritagliaIlLogo($uno, $logo);

            $esito ? $ritagliati++ : $lasciati++;
        }

        $this->newLine();
        $this->info("Loghi ritagliati: {$ritagliati}. Lasciati com'erano: {$lasciati}.");

        return self::SUCCESS;
    }

    private function ritagliaIlLogo(Sponsor $sponsor, Media $logo): bool
    {
        try {
            // Non `getPath()`: in produzione il file sta su Spaces e non c'e'
            // nessun percorso sul filesystem da aprire.
            $byte = Storage::disk($logo->disk)->get($logo->getPathRelativeToRoot());
        } catch (\Throwable $errore) {
            $this->warn("  ⚠ {$sponsor->name}: {$errore->getMessage()}");

            return false;
        }

        if (! is_string($byte) || $byte === '') {
            return false;
        }

        $prima = getimagesizefromstring($byte);
        $ritagliato = RitaglioDelMargine::ritaglia($byte);

        if ($ritagliato === null) {
            return false;
        }

        $dopo = getimagesizefromstring($ritagliato);

        $this->line(sprintf(
            '  ✓ %-34s %dx%d → %dx%d',
            mb_substr($sponsor->name, 0, 34),
            $prima[0] ?? 0,
            $prima[1] ?? 0,
            $dopo[0] ?? 0,
            $dopo[1] ?? 0
        ));

        if ($this->option('prova')) {
            return true;
        }

        $nome = pathinfo($logo->file_name, PATHINFO_FILENAME).'.png';

        $sponsor->clearMediaCollection('sponsors');
        $sponsor->addMediaFromString($ritagliato)
            ->usingFileName($nome)
            ->toMediaCollection('sponsors');

        return true;
    }
}
