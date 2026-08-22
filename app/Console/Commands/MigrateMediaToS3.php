<?php

namespace App\Console\Commands;

use App\Exceptions\MediaProcessingException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Migra i file media dal disco 'public' (locale) al disco 's3' (DigitalOcean Spaces).
 *
 * Problema: i media caricati in locale durante lo sviluppo restano con disk='public'
 * nel database. Su DigitalOcean App Platform il filesystem locale è effimero,
 * quindi quei file non esistono in produzione → spinner infinito nel CMS.
 *
 * Questo comando:
 * 1. Trova tutti i record media con disk='public'
 * 2. Copia il file originale + conversioni da public → s3
 * 3. Aggiorna il record nel DB per puntare al disco s3
 */
class MigrateMediaToS3 extends Command
{
    private const ESITO_MIGRATO = 'migrato';

    private const ESITO_SALTATO = 'saltato';

    private const ESITO_ERRORE = 'errore';

    protected $signature = 'media:migrate-to-s3
                            {--dry-run : Mostra cosa verrebbe fatto senza eseguirlo}
                            {--collection= : Filtra per collection name (es. rosters_official)}';

    protected $description = 'Migra i file media dal disco locale (public) a S3 (DigitalOcean Spaces)';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $mediaItems = $this->mediaDaMigrare();

        if ($mediaItems->isEmpty()) {
            $this->info('✅ Nessun media da migrare — tutti i file sono già su S3.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d file media da "public" a "s3"...',
            $isDryRun ? '[DRY-RUN] Verranno migrati' : 'Migrazione di',
            $mediaItems->count()
        ));

        $this->newLine();

        $migrati = 0;
        $saltati = 0;
        $errori = 0;

        foreach ($mediaItems as $media) {
            /** @var Media $media */
            $relativePath = $media->id.'/'.$media->file_name;

            $this->line(sprintf(
                '  [%d] %s (%s) → %s',
                $media->id,
                $media->file_name,
                $media->collection_name,
                $relativePath,
            ));

            match ($this->migraUno($media, $relativePath, $isDryRun)) {
                self::ESITO_MIGRATO => $migrati++,
                self::ESITO_SALTATO => $saltati++,
                default => $errori++,
            };
        }

        $this->newLine();
        $this->info("Riepilogo: {$migrati} migrati, {$saltati} saltati, {$errori} errori.");

        if ($isDryRun) {
            $this->warn('⚠️  Modalità dry-run: nessun file è stato effettivamente spostato.');
            $this->line('Rimuovi --dry-run per eseguire la migrazione.');
        }

        return $errori > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * I media ancora sul disco locale, eventualmente di una sola collezione.
     *
     * @return Collection<int, Media>
     */
    private function mediaDaMigrare(): Collection
    {
        $query = Media::where('disk', 'public');

        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
        }

        return $query->get();
    }

    /**
     * Sposta un media e le sue conversioni su S3, poi aggiorna il record.
     */
    private function migraUno(Media $media, string $relativePath, bool $isDryRun): string
    {
        if ($isDryRun) {
            return self::ESITO_MIGRATO;
        }

        $sourceDisk = Storage::disk('public');

        if (! $sourceDisk->exists($relativePath)) {
            $this->warn("    ⚠️  File sorgente non trovato su disco 'public', skip.");

            return self::ESITO_SALTATO;
        }

        try {
            $stream = $sourceDisk->readStream($relativePath);

            if (! $stream) {
                throw new MediaProcessingException('Impossibile leggere il file sorgente.');
            }

            Storage::disk('s3')->writeStream($relativePath, $stream, ['visibility' => 'public']);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->copiaLeConversioni($media);

            $media->update([
                'disk' => 's3',
                'conversions_disk' => 's3',
            ]);

            $this->info('    ✅ Migrato con successo.');

            return self::ESITO_MIGRATO;
        } catch (\Throwable $e) {
            $this->error("    ❌ Errore: {$e->getMessage()}");

            return self::ESITO_ERRORE;
        }
    }

    /**
     * Le miniature e i ritagli gia' prodotti seguono l'originale: rigenerarli
     * costerebbe una conversione per ogni file.
     */
    private function copiaLeConversioni(Media $media): void
    {
        $sourceDisk = Storage::disk('public');
        $conversionsPath = $media->id.'/conversions';

        if (! $sourceDisk->exists($conversionsPath)) {
            return;
        }

        foreach ($sourceDisk->files($conversionsPath) as $conversionFile) {
            $convStream = $sourceDisk->readStream($conversionFile);

            if (! $convStream) {
                continue;
            }

            Storage::disk('s3')->writeStream($conversionFile, $convStream, ['visibility' => 'public']);

            if (is_resource($convStream)) {
                fclose($convStream);
            }

            $this->line("    📎 Conversione: {$conversionFile}");
        }
    }
}
