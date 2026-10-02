<?php

namespace App\Console\Commands;

use App\Support\FotoAlleggerita;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Ripara le foto PNG rimaste senza conversioni perche' il loro profilo
 * colore difettoso faceva fallire GD (FotoAlleggerita::togliIlProfiloDalPng):
 * toglie il profilo dall'originale e rigenera le conversioni. Il 02/10/2026
 * erano otto foto prodotto, invisibili in vetrina.
 */
class RiparaFotoPng extends Command
{
    protected $signature = 'foto:ripara-png {--dry-run : elenca senza modificare}';

    protected $description = 'Toglie il profilo colore difettoso dai PNG senza conversioni e le rigenera';

    public function handle(FileManipulator $conversioni): int
    {
        $media = Media::query()
            ->where('mime_type', 'image/png')
            // Colonna JSON: '[]' non si confronta come stringa.
            ->where(fn ($q) => $q->whereNull('generated_conversions')
                ->orWhereRaw('JSON_LENGTH(generated_conversions) = 0'))
            ->orderBy('id')
            ->get();

        $riparate = 0;
        $fallite = 0;

        foreach ($media as $foto) {
            $disco = Storage::disk($foto->disk);
            $percorso = $foto->getPathRelativeToRoot();

            try {
                $pulito = FotoAlleggerita::pngSenzaProfilo((string) $disco->get($percorso));
            } catch (Throwable $e) {
                $this->warn("#{$foto->id}: originale illeggibile ({$e->getMessage()})");
                $fallite++;

                continue;
            }

            if ($pulito === null) {
                $this->line("#{$foto->id}: nessun profilo da togliere, la lascio");

                continue;
            }

            $this->line("#{$foto->id} ({$foto->model_type} {$foto->model_id}): profilo da togliere");

            if ($this->option('dry-run')) {
                continue;
            }

            try {
                $disco->put($percorso, $pulito, [
                    'visibility' => 'public',
                    'ContentType' => 'image/png',
                    ...config('media-library.remote.extra_headers', []),
                ]);
                $foto->size = strlen($pulito);
                $foto->save();

                $conversioni->createDerivedFiles($foto);
                $riparate++;
            } catch (Throwable $e) {
                $this->error("#{$foto->id}: {$e->getMessage()}");
                $fallite++;
            }
        }

        $this->info("PNG senza conversioni: {$media->count()}, riparate: {$riparate}, fallite: {$fallite}");

        return $fallite > 0 ? self::FAILURE : self::SUCCESS;
    }
}
