<?php

namespace App\Services;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class GalleryUploadService
{
    /**
     * Elabora il caricamento multiplo di foto da un evento galleria.
     * Genera record GalleryImage separati e accoda il job di analisi AI.
     *
     * @param  FileUpload  $component  Il componente FileUpload di Filament
     * @param  mixed  $state  Lo stato del componente (array di file)
     * @param  GalleryEvent  $record  L'evento galleria corrente
     */
    public static function processUploads(FileUpload $component, $state, GalleryEvent $record): void
    {
        if (! is_array($state)) {
            return;
        }

        foreach ($state as $file) {
            $image = new GalleryImage;
            $image->gallery_event_id = $record->id;
            $image->title = $record->title;
            $image->category = $record->category;
            $image->is_active = $record->is_active;
            $image->save();

            if ($file instanceof TemporaryUploadedFile) {
                $image->addMedia($file->getRealPath())
                    ->toMediaCollection('gallery');
            } else {
                // It's a string (path on disk)
                $disk = $component->getDiskName();
                $image->addMediaFromDisk($file, $disk)
                    ->toMediaCollection('gallery');
            }

            AnalyzeGalleryImageJob::dispatch($image);
        }

        // Clear the state so they aren't processed again on next save
        $component->state([]);
    }
}
