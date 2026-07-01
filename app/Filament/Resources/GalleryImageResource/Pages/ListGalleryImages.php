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
                        ->maxSize(51200)
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('2400')
                        ->imageResizeTargetHeight('2400')
                        ->imageResizeUpscale(false)
                        ->directory('temp_gallery_uploads')
                        ->required(),
                ])
                ->action(function (array $data, Actions\Action $action) {
                    $event = GalleryEvent::create([
                        'title' => $data['title'],
                        'event_date' => $data['event_date'],
                        'category' => $data['category'],
                        'description' => $data['description'],
                        'is_active' => true,
                    ]);

                    $uploadedPhotos = $data['uploaded_photos'] ?? [];

                    foreach ($uploadedPhotos as $file) {
                        $image = new GalleryImage;
                        $image->gallery_event_id = $event->id;
                        $image->title = $event->title;
                        $image->category = $event->category;
                        $image->is_active = true;
                        $image->save();

                        // Access the disk used by FileUpload (usually default public or s3)
                        // In an action, file paths are returned as strings if they are saved.
                        if (is_string($file)) {
                            // File was moved to the directory
                            $image->addMediaFromDisk($file, config('filesystems.default'))
                                ->toMediaCollection('gallery');
                        }

                        AnalyzeGalleryImageJob::dispatch($image);
                    }

                    Notification::make()
                        ->title('Evento Creato')
                        ->body(count($uploadedPhotos).' foto in fase di analisi AI.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('Carica Singola Foto'),
        ];
    }
}
