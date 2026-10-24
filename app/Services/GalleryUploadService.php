<?php

namespace App\Services;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Support\FotoAlleggerita;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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

        $disk = $component->getDiskName();
        $duplicates = 0;
        $uploaded = 0;
        /** @var array<int, int> $albumDeiDoppioni */
        $albumDeiDoppioni = [];

        foreach ($state as $file) {
            // Calcola hash del file per rilevamento duplicati
            $fileHash = static::computeFileHash($file, $disk);

            $albumGiaUsato = $fileHash ? self::albumDelDoppione($fileHash) : null;

            if ($albumGiaUsato !== null) {
                $duplicates++;
                $albumDeiDoppioni[$albumGiaUsato] = $albumGiaUsato;
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
                ->body($duplicates.' foto già presenti nel sistema e saltate. '.$uploaded.' nuove foto caricate.'
                    .' '.self::doveSonoIDoppioni($albumDeiDoppioni, $record))
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

            // Un PNG con un profilo colore difettoso fa fallire le
            // conversioni sul GD di Linux: si toglie prima (FotoAlleggerita).
            FotoAlleggerita::togliIlProfiloDalPng($tempPath);

            $image->addMedia($tempPath)
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('gallery');
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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

    /**
     * L'album che contiene già la foto con questa impronta, o null se la foto
     * è nuova. Una foto senza album conta comunque come doppione (0).
     */
    public static function albumDelDoppione(string $hash): ?int
    {
        $foto = GalleryImage::where('file_hash', $hash)->first(['id', 'gallery_event_id']);

        return $foto ? (int) $foto->gallery_event_id : null;
    }

    /**
     * Dice dove stanno le foto saltate. Senza, chi ricarica in un album nuovo
     * le stesse foto vede solo «già presenti» e un album vuoto, e non sa che
     * le foto sono rimaste nel primo album creato (15/10/2026: tre album per
     * la stessa gara, due vuoti).
     *
     * Senza album di destinazione (`null`) tutti gli album contano come altri.
     *
     * @param  array<int, int>  $albumIds
     */
    public static function doveSonoIDoppioni(array $albumIds, ?GalleryEvent $record): string
    {
        $altri = GalleryEvent::whereKey(array_diff($albumIds, $record ? [$record->id] : []))
            ->orderBy('id')
            ->get()
            ->map(fn (GalleryEvent $album) => '«'.$album->title.'»'
                .($album->event_date ? ' del '.$album->event_date->format('d/m/Y') : ' (senza data)')
                .' [id '.$album->id.']');

        if ($altri->isEmpty()) {
            return $record && in_array($record->id, $albumIds, true) ? 'Sono già in questo album.' : 'Sono in archivio fuori da un album.';
        }

        return 'Si trovano già '.($altri->count() === 1 ? 'nell\'album ' : 'negli album ').$altri->join(', ').'.';
    }
}
