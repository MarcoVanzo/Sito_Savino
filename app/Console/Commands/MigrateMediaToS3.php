<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
    protected $signature = 'media:migrate-to-s3
                            {--dry-run : Mostra cosa verrebbe fatto senza eseguirlo}
                            {--collection= : Filtra per collection name (es. rosters_official)}';

    protected $description = 'Migra i file media dal disco locale (public) a S3 (DigitalOcean Spaces)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $collection = $this->option('collection');

        $query = Media::where('disk', 'public');

        if ($collection) {
            $query->where('collection_name', $collection);
        }

        $mediaItems = $query->get();

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

        $sourceDisk = Storage::disk('public');
        $targetDisk = Storage::disk('s3');

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

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

            if ($isDryRun) {
                $migrated++;

                continue;
            }

            // Verifica che il file sorgente esista
            if (! $sourceDisk->exists($relativePath)) {
                $this->warn("    ⚠️  File sorgente non trovato su disco 'public', skip.");
                $skipped++;

                continue;
            }

            try {
                // Copia il file originale
                $stream = $sourceDisk->readStream($relativePath);
                if (! $stream) {
                    throw new \RuntimeException('Impossibile leggere il file sorgente.');
                }

                $targetDisk->writeStream($relativePath, $stream, ['visibility' => 'public']);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Copia le conversioni (es. thumb, card)
                $conversionsPath = $media->id.'/conversions';
                if ($sourceDisk->exists($conversionsPath)) {
                    foreach ($sourceDisk->files($conversionsPath) as $conversionFile) {
                        $convStream = $sourceDisk->readStream($conversionFile);
                        if ($convStream) {
                            $targetDisk->writeStream($conversionFile, $convStream, ['visibility' => 'public']);
                            if (is_resource($convStream)) {
                                fclose($convStream);
                            }
                            $this->line("    📎 Conversione: {$conversionFile}");
                        }
                    }
                }

                // Aggiorna il record nel database
                $media->update([
                    'disk' => 's3',
                    'conversions_disk' => 's3',
                ]);

                $this->info("    ✅ Migrato con successo.");
                $migrated++;

            } catch (\Throwable $e) {
                $this->error("    ❌ Errore: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Riepilogo: {$migrated} migrati, {$skipped} saltati, {$errors} errori.");

        if ($isDryRun) {
            $this->warn('⚠️  Modalità dry-run: nessun file è stato effettivamente spostato.');
            $this->line('Rimuovi --dry-run per eseguire la migrazione.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
