<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FixPostMedia extends Command
{
    protected $signature = 'fix:post-media
        {--dry-run : Mostra cosa verrebbe fatto senza modificare nulla}
        {--export-path=/Users/marcovanzo/wp_export_savino/data/media_files : Percorso cartella media_files dell\'export WP}';

    protected $description = 'Corregge i media dei Post: rinomina file UUID su S3 e carica i file mancanti dall\'export WordPress';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $exportPath = $this->option('export-path');
        $disk = Storage::disk('s3');

        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  Fix Post Media — S3 rename & upload                    ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🏃 MODALITÀ DRY-RUN — nessuna modifica verrà applicata');
            $this->newLine();
        }

        // Carica tutti i media dei Post su disco s3
        $postMedia = Media::where('model_type', 'App\\Models\\Post')
            ->where('disk', 's3')
            ->get();

        $this->info("Media dei Post con disk=s3: {$postMedia->count()}");
        $this->newLine();

        $renamed = 0;
        $uploaded = 0;
        $alreadyOk = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($postMedia->count());
        $bar->start();

        foreach ($postMedia as $media) {
            $expectedPath = $media->id.'/'.$media->file_name;

            // 1. Controlla se il file esiste già con il nome corretto
            if ($disk->exists($expectedPath)) {
                $alreadyOk++;
                $bar->advance();

                continue;
            }

            // 2. Controlla se esiste un file con nome UUID nella stessa directory
            $s3Files = $disk->allFiles($media->id.'/');

            if (! empty($s3Files)) {
                // File esiste con nome diverso → rinomina (copy + delete)
                $oldPath = $s3Files[0];
                $oldName = basename($oldPath);

                if ($dryRun) {
                    $this->newLine();
                    $this->line("  [RENAME] Media {$media->id}: {$oldName} → {$media->file_name}");
                } else {
                    try {
                        $disk->copy($oldPath, $expectedPath);
                        $disk->delete($oldPath);
                        $this->newLine();
                        $this->info("  ✅ Rinominato media {$media->id}: {$oldName} → {$media->file_name}");
                    } catch (\Throwable $e) {
                        $this->newLine();
                        $this->error("  ❌ Errore rename media {$media->id}: {$e->getMessage()}");
                        $failed++;
                        $bar->advance();

                        continue;
                    }
                }
                $renamed++;
                $bar->advance();

                continue;
            }

            // 3. File mancante su S3 → carica da export locale
            $localPath = $exportPath.'/'.$media->file_name;

            if (! file_exists($localPath)) {
                // Prova senza estensione diversa o con varianti comuni
                $found = $this->findLocalFile($exportPath, $media->file_name);
                if ($found) {
                    $localPath = $found;
                } else {
                    $this->newLine();
                    $this->warn("  ⚠️  Media {$media->id}: file locale non trovato ({$media->file_name})");
                    $failed++;
                    $bar->advance();

                    continue;
                }
            }

            if ($dryRun) {
                $this->newLine();
                $size = round(filesize($localPath) / 1024);
                $this->line("  [UPLOAD] Media {$media->id}: {$media->file_name} ({$size} KB)");
            } else {
                try {
                    $stream = fopen($localPath, 'r');
                    $disk->put($expectedPath, $stream, 'public');
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $this->newLine();
                    $this->info("  ✅ Caricato media {$media->id}: {$media->file_name}");
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->error("  ❌ Errore upload media {$media->id}: {$e->getMessage()}");
                    $failed++;
                    $bar->advance();

                    continue;
                }
            }
            $uploaded++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('══════════════════════════════════════════');
        $this->info("  Già OK:      {$alreadyOk}");
        $this->info("  Rinominati:  {$renamed}");
        $this->info("  Caricati:    {$uploaded}");
        if ($failed > 0) {
            $this->warn("  Falliti:     {$failed}");
        }
        $this->info('══════════════════════════════════════════');

        return self::SUCCESS;
    }

    /**
     * Cerca il file locale con possibili varianti di nome (estensione case-insensitive, jpeg/jpg swap).
     */
    private function findLocalFile(string $dir, string $filename): ?string
    {
        // Prova il nome esatto
        $path = $dir.'/'.$filename;
        if (file_exists($path)) {
            return $path;
        }

        // Prova swap jpeg <-> jpg
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);

        $alternatives = [];
        if (strtolower($ext) === 'jpeg') {
            $alternatives[] = $base.'.jpg';
            $alternatives[] = $base.'.JPG';
        } elseif (strtolower($ext) === 'jpg') {
            $alternatives[] = $base.'.jpeg';
            $alternatives[] = $base.'.JPEG';
        }

        foreach ($alternatives as $alt) {
            $altPath = $dir.'/'.$alt;
            if (file_exists($altPath)) {
                return $altPath;
            }
        }

        // Cerca case-insensitive tramite glob
        $pattern = $dir.'/'.$base.'.*';
        $matches = glob($pattern, GLOB_NOSORT);
        if (! empty($matches)) {
            return $matches[0];
        }

        return null;
    }
}
