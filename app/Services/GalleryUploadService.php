<?php

namespace App\Services;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class GalleryUploadService
{
    /**
     * Elabora il caricamento multiplo di foto da un evento galleria.
     * Genera record GalleryImage separati e accoda il job di analisi AI.
     * Rileva e salta i duplicati basandosi sull'hash SHA-256 del file.
     */
    public static function processUploads(FileUpload $component, $state, GalleryEvent $record): void
    {
        if (! is_array($state)) {
            return;
        }

        $disk = $component->getDiskName() ?? config('filesystems.default');
        $duplicates = 0;
        $uploaded = 0;

        foreach ($state as $file) {
            // Calcola hash del file per rilevamento duplicati
            $fileHash = static::computeFileHash($file, $disk);

            if ($fileHash && static::isDuplicate($fileHash)) {
                $duplicates++;
                Log::info('Gallery upload: duplicato rilevato', ['hash' => $fileHash]);

                continue;
            }

            $image = self::creaLaFoto($record, $fileHash);
            self::allegaIlFile($image, $file, $disk);

            AnalyzeGalleryImageJob::dispatch($image);
            $uploaded++;
        }

        // Clear the state so they aren't processed again on next save
        $component->state([]);

        if ($duplicates > 0) {
            Notification::make()
                ->title('Duplicati rilevati')
                ->body($duplicates.' foto già presenti nel sistema e saltate. '.$uploaded.' nuove foto caricate.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * La riga della foto, che eredita dall'album titolo, categoria e
     * pubblicazione.
     */
    private static function creaLaFoto(GalleryEvent $record, ?string $fileHash): GalleryImage
    {
        $image = new GalleryImage;
        $image->gallery_event_id = $record->id;
        $image->title = $record->title;
        $image->category = $record->category;
        $image->is_active = $record->is_active;
        $image->file_hash = $fileHash;
        $image->save();

        return $image;
    }

    /**
     * Porta il file caricato nella collezione della foto.
     *
     * Un caricamento appena fatto vive in un file temporaneo di Livewire: va
     * copiato su disco prima di allegarlo, e il temporaneo va tolto anche
     * quando l'allegato fallisce, altrimenti resta sul container.
     */
    private static function allegaIlFile(GalleryImage $image, mixed $file, string $disk): void
    {
        if (! $file instanceof TemporaryUploadedFile) {
            $image->addMediaFromDisk($file, $disk)->toMediaCollection('gallery');

            return;
        }

        $tempPath = sys_get_temp_dir().'/'.uniqid('upload_').'_'.$file->getClientOriginalName();

        try {
            $stream = $file->readStream();
            file_put_contents($tempPath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $image->addMedia($tempPath)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('gallery');
        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            throw $e;
        }
    }

    /**
     * Calcola l'hash SHA-256 del file.
     */
    public static function computeFileHash(mixed $file, string $disk = 'local'): ?string
    {
        try {
            if ($file instanceof TemporaryUploadedFile) {
                $stream = $file->readStream();
                $content = stream_get_contents($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                return hash('sha256', $content);
            }

            if (is_string($file)) {
                $fullPath = Storage::disk($disk)->path($file);
                if (file_exists($fullPath)) {
                    return hash_file('sha256', $fullPath);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gallery: impossibile calcolare hash del file', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Verifica se un file con lo stesso hash esiste già nel database.
     */
    public static function isDuplicate(string $hash): bool
    {
        return GalleryImage::where('file_hash', $hash)->exists();
    }
}
