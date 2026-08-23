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
                ->action(fn (array $data) => $this->creaLEventoConLeFoto($data)),
            Actions\CreateAction::make()
                ->label('Carica Singola Foto'),
        ];
    }

    /**
     * Crea l'album e ci porta dentro le foto appena caricate.
     *
     * @param  array<string, mixed>  $data
     */
    private function creaLEventoConLeFoto(array $data): void
    {
        try {
            $event = GalleryEvent::create([
                'title' => $data['title'],
                'event_date' => $data['event_date'],
                'category' => $data['category'],
                'description' => $data['description'],
                'is_active' => true,
            ]);

            $duplicati = 0;
            $caricate = 0;

            foreach ($data['uploaded_photos'] ?? [] as $key => $file) {
                if (! is_string($file)) {
                    Log::warning('Gallery upload: unexpected file type', [
                        'key' => $key,
                        'type' => get_debug_type($file),
                    ]);

                    continue;
                }

                $fileHash = $this->improntaDelFile($file);

                if ($fileHash && GalleryImage::where('file_hash', $fileHash)->exists()) {
                    $duplicati++;

                    continue;
                }

                $this->aggiungiLaFoto($event, $file, $fileHash);
                $caricate++;
            }

            $body = $caricate.' foto in fase di analisi AI.';

            if ($duplicati > 0) {
                $body .= ' '.$duplicati.' duplicati saltati.';
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
                ->body('Si è verificato un errore durante il salvataggio. Controlla i log per i dettagli.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Impronta del file caricato, per riconoscere una foto gia' in archivio.
     */
    private function improntaDelFile(string $file): ?string
    {
        $fullPath = storage_path('app/private/'.$file);

        return file_exists($fullPath) ? hash_file('sha256', $fullPath) : null;
    }

    /**
     * La foto eredita dall'album titolo e categoria, e parte subito per
     * l'analisi dei volti.
     */
    private function aggiungiLaFoto(GalleryEvent $event, string $file, ?string $fileHash): void
    {
        $image = new GalleryImage;
        $image->gallery_event_id = $event->id;
        $image->title = $event->title;
        $image->category = $event->category;
        $image->is_active = true;
        $image->file_hash = $fileHash;
        $image->save();

        $image->addMediaFromDisk($file, 'local')->toMediaCollection('gallery');

        AnalyzeGalleryImageJob::dispatch($image);
    }
}
