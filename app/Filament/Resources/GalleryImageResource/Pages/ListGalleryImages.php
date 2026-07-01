<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

use Illuminate\Support\Facades\Log;

class ListGalleryImages extends ListRecords
{
    protected static string $resource = GalleryImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_event')
                ->label('Crea Evento e Carica Foto')
                ->icon('heroicon-o-folder-plus')
                ->color('success')
                ->modalSubmitActionLabel('Salva')
                ->form([
                    TextInput::make('title')
                        ->label('Titolo Evento')
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('event_date')
                        ->label('Data Evento'),
                    Select::make('category')
                        ->label('Categoria')
                        ->required()
                        ->options([
                            'Partite' => 'Partite',
                            'Allenamenti' => 'Allenamenti',
                            'Eventi' => 'Eventi',
                            'Tifosi' => 'Tifosi',
                            'Backstage' => 'Backstage',
                        ])
                        ->default('Partite'),
                    Textarea::make('description')
                        ->label('Descrizione'),
                    FileUpload::make('uploaded_photos')
                        ->label('Cartella Foto')
                        ->multiple()
                        ->image()
                        ->disk('local')
                        ->maxSize(51200)
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('2400')
                        ->imageResizeTargetHeight('2400')
                        ->imageResizeUpscale(false)
                        ->directory('temp_gallery_uploads')
                        ->required(),
                ])
                ->action(function (array $data, Actions\Action $action) {
                    try {
                        $event = GalleryEvent::create([
                            'title' => $data['title'],
                            'event_date' => $data['event_date'],
                            'category' => $data['category'],
                            'description' => $data['description'],
                            'is_active' => true,
                        ]);

                        $uploadedPhotos = $data['uploaded_photos'] ?? [];
                        $duplicates = 0;
                        $uploaded = 0;

                        foreach ($uploadedPhotos as $key => $file) {
                            // Rilevamento duplicati via hash
                            $fileHash = null;
                            if (is_string($file)) {
                                $fullPath = storage_path('app/private/' . $file);
                                if (file_exists($fullPath)) {
                                    $fileHash = hash_file('sha256', $fullPath);
                                }
                            }

                            if ($fileHash && GalleryImage::where('file_hash', $fileHash)->exists()) {
                                $duplicates++;
                                continue;
                            }

                            $image = new GalleryImage;
                            $image->gallery_event_id = $event->id;
                            $image->title = $event->title;
                            $image->category = $event->category;
                            $image->is_active = true;
                            $image->file_hash = $fileHash;
                            $image->save();

                            if (is_string($file)) {
                                $image->addMediaFromDisk($file, 'local')
                                    ->toMediaCollection('gallery');
                            } else {
                                Log::warning('Gallery upload: unexpected file type', [
                                    'key' => $key,
                                    'type' => get_class($file),
                                ]);
                            }

                            AnalyzeGalleryImageJob::dispatch($image);
                            $uploaded++;
                        }

                        $body = $uploaded . ' foto in fase di analisi AI.';
                        if ($duplicates > 0) {
                            $body .= ' ' . $duplicates . ' duplicati saltati.';
                        }

                        Notification::make()
                            ->title('Evento Creato')
                            ->body($body)
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Log::error('Gallery upload failed', [
                            'error' => $e->getMessage(),
                            'file' => $e->getFile().':'.$e->getLine(),
                        ]);

                        Notification::make()
                            ->title('Errore nel salvataggio')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Actions\CreateAction::make()
                ->label('Carica Singola Foto'),
        ];
    }
}

