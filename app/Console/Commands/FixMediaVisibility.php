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
        $disk = Storage::disk('s3');
        $dryRun = $this->option('dry-run');

        $media = Media::where('disk', 's3')->get();

        $this->info("Found {$media->count()} media items on S3 disk.");

        $fixed = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($media->count());
        $bar->start();

        foreach ($media as $item) {
            $path = $item->getPath();

            try {
                if ($dryRun) {
                    $this->line(" Would fix: {$path}");
                } else {
                    $disk->setVisibility($path, 'public');
                }
                $fixed++;
            } catch (\Exception $e) {
                $errors++;
                $this->error(" Error for {$path}: {$e->getMessage()}");
            }

            // Also fix conversions
            $conversionsPath = $item->getPath() ? dirname($item->getPath()).'/conversions/' : null;
            if ($conversionsPath) {
                try {
                    $conversionFiles = $disk->files($conversionsPath);
                    foreach ($conversionFiles as $file) {
                        if ($dryRun) {
                            $this->line(" Would fix conversion: {$file}");
                        } else {
                            $disk->setVisibility($file, 'public');
                        }
                        $fixed++;
                    }
                } catch (\Exception $e) {
                    // conversions dir may not exist, that's ok
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Fixed visibility for {$fixed} files. Errors: {$errors}.");

        return self::SUCCESS;
    }
}
