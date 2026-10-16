<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Traits\InvalidaLaCacheDopoIlRiordino;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Services\GalleryUploadService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ListGalleryImages extends ListRecords
{
    use InvalidaLaCacheDopoIlRiordino;

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
                    // Obbligatoria come nella scheda dell'album: la gallery
                    // pubblica ordina gli album per data.
                    DatePicker::make('event_date')
                        ->label('Data Evento')
                        ->required(),
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
     * Crea l'album e ci porta dentro le foto appena caricate. I doppioni si
     * contano prima: se tutte le foto sono già in archivio l'album non nasce
     * (15/10/2026: tre album per la stessa gara, due vuoti) e il messaggio
     * dice in quale album stanno.
     *
     * @param  array<string, mixed>  $data
     */
    private function creaLEventoConLeFoto(array $data): void
    {
        try {
            /** @var array<int, array{0: string, 1: ?string}> $nuove */
            $nuove = [];
            /** @var array<int, int> $albumDeiDoppioni */
            $albumDeiDoppioni = [];
            $duplicati = 0;
            // La stessa foto due volte nello stesso caricamento (un export
            // ripetuto): nessuna delle due e' ancora in archivio.
            /** @var array<string, true> $impronteViste */
            $impronteViste = [];

            foreach ($data['uploaded_photos'] ?? [] as $key => $file) {
                if (! is_string($file)) {
                    Log::warning('Gallery upload: unexpected file type', [
                        'key' => $key,
                        'type' => get_debug_type($file),
                    ]);

                    continue;
                }

                $fileHash = $this->improntaDelFile($file);
                $albumGiaUsato = $fileHash ? GalleryUploadService::albumDelDoppione($fileHash) : null;

                if ($albumGiaUsato !== null) {
                    $duplicati++;
                    $albumDeiDoppioni[$albumGiaUsato] = $albumGiaUsato;

                    continue;
                }

                if ($fileHash !== null && isset($impronteViste[$fileHash])) {
                    $duplicati++;

                    continue;
                }

                if ($fileHash !== null) {
                    $impronteViste[$fileHash] = true;
                }

                $nuove[] = [$file, $fileHash];
            }

            if ($nuove === []) {
                // Senza doppioni i file non erano leggibili: «Le 0 foto sono già
                // in archivio» non diceva niente.
                Notification::make()
                    ->title('Nessun album creato')
                    ->body($duplicati > 0
                        ? 'Le '.$duplicati.' foto sono già in archivio. '.GalleryUploadService::doveSonoIDoppioni($albumDeiDoppioni, null)
                        : 'Nessuna foto leggibile fra quelle caricate: riprova il caricamento.')
                    ->warning()
                    ->persistent()
                    ->send();

                return;
            }

            $event = GalleryEvent::create([
                'title' => $data['title'],
                'event_date' => $data['event_date'],
                'category' => $data['category'],
                'description' => $data['description'],
                'is_active' => true,
            ]);

            foreach ($nuove as [$file, $fileHash]) {
                $this->aggiungiLaFoto($event, $file, $fileHash);
            }

            $body = count($nuove).' foto in fase di analisi AI.';

            if ($duplicati > 0) {
                $body .= ' '.$duplicati.' duplicati saltati. '
                    .GalleryUploadService::doveSonoIDoppioni($albumDeiDoppioni, $event);
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
        // Il percorso lo dà il disco, non si compone a mano: il file arriva dal
        // campo di caricamento, che dichiara `->disk('local')`, e cablare qui
        // `storage_path('app/private/…')` significa che basta un cambio di
        // radice del disco perché l'impronta torni null. Non salterebbe niente
        // in modo visibile: semplicemente i doppioni ricomincerebbero a
        // passare, senza un errore da nessuna parte.
        $percorso = Storage::disk('local')->path($file);

        return file_exists($percorso) ? hash_file('sha256', $percorso) : null;
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
