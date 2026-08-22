<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FixMediaVisibility extends Command
{
    protected $signature = 'media:fix-visibility {--dry-run : Show what would be changed without changing}';

    protected $description = 'Set all S3 media files to public visibility';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $media = Media::where('disk', 's3')->get();

        $this->info("Found {$media->count()} media items on S3 disk.");

        $sistemati = 0;
        $errori = 0;

        $bar = $this->output->createProgressBar($media->count());
        $bar->start();

        foreach ($media as $item) {
            $path = $item->getPath();

            try {
                $this->rendiPubblico($path, $dryRun);
                $sistemati++;
            } catch (\Throwable $e) {
                $errori++;
                $this->error(" Error for {$path}: {$e->getMessage()}");
            }

            $sistemati += $this->rendiPubblicheLeConversioni($path, $dryRun);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Fixed visibility for {$sistemati} files. Errors: {$errori}.");

        return self::SUCCESS;
    }

    private function rendiPubblico(string $path, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line(" Would fix: {$path}");

            return;
        }

        Storage::disk('s3')->setVisibility($path, 'public');
    }

    /**
     * Miniature e ritagli stanno in una cartella accanto all'originale, che
     * puo' benissimo non esistere: in quel caso non c'e' niente da sistemare.
     *
     * @return int quanti file sono stati resi pubblici
     */
    private function rendiPubblicheLeConversioni(?string $path, bool $dryRun): int
    {
        if (! $path) {
            return 0;
        }

        try {
            $files = Storage::disk('s3')->files(dirname($path).'/conversions/');
        } catch (\Throwable) {
            return 0;
        }

        foreach ($files as $file) {
            if ($dryRun) {
                $this->line(" Would fix conversion: {$file}");

                continue;
            }

            Storage::disk('s3')->setVisibility($file, 'public');
        }

        return count($files);
    }
}
