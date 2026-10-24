<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryEventResource\Pages\CreateGalleryEvent;
use App\Filament\Resources\GalleryEventResource\Pages\EditGalleryEvent;
use App\Filament\Resources\GalleryEventResource\Pages\ListGalleryEvents;
use App\Filament\Resources\GalleryEventResource\RelationManagers\GalleryImagesRelationManager;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Services\GalleryUploadService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class GalleryEventResource extends Resource
{
    use Translatable;

    protected static ?string $model = GalleryEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Foto Gallery';

    protected static ?string $modelLabel = 'Evento Galleria';

    protected static ?string $pluralModelLabel = 'Eventi Galleria';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'gallery';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Evento')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255),
                        // Obbligatoria: la gallery pubblica ordina gli album per
                        // data, e uno senza finisce in fondo all'elenco.
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
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Caricamento Foto')
                    ->description('Le foto caricate qui verranno salvate, associate all\'evento e analizzate dall\'Intelligenza Artificiale automaticamente.')
                    ->schema([
                        FileUpload::make('uploaded_photos')
                            ->label('Carica Nuove Foto')
                            ->multiple()
                            ->image()
                            ->disk('local')
                            ->maxSize(51200)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('2400')
                            ->imageResizeTargetHeight('2400')
                            ->imageResizeUpscale(false)
                            ->directory('temp_gallery_uploads')
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function (FileUpload $component, $state, GalleryEvent $record) {
                                GalleryUploadService::processUploads($component, $state, $record);
                            }),
                    ]),
            ]);
    }

    /**
     * A che punto e' l'analisi dei volti sulle foto dell'album.
     */
    private static function statoDellAnalisi(GalleryEvent $record): string
    {
        $total = $record->gallery_images_count ?? 0;

        if ($total === 0) {
            return 'empty';
        }

        $analyzed = $record->ai_analyzed_images_count ?? 0;

        if ($analyzed === $total) {
            return 'complete';
        }

        return $analyzed > 0 ? 'partial' : 'none';
    }

    /**
     * "12/30", oppure un trattino se l'album e' vuoto.
     */
    private static function avanzamentoDellAnalisi(string $state, GalleryEvent $record): string
    {
        $total = $record->gallery_images_count ?? 0;

        if ($total === 0) {
            return '—';
        }

        $analyzed = $record->ai_analyzed_images_count ?? 0;

        return match ($state) {
            'complete' => '✓ '.$analyzed.'/'.$total,
            'partial' => $analyzed.'/'.$total,
            'none' => '0/'.$total,
            default => '—',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'galleryImages as ai_analyzed_images_count' => fn ($q) => $q->whereNotNull('ai_analyzed_at'),
            ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('event_date')
                    ->label('Data Evento')
                    ->date()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->sortable(),
                TextColumn::make('gallery_images_count')
                    ->label('Numero Foto')
                    ->counts('galleryImages'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                TextColumn::make('ai_status')
                    ->label('Analisi AI')
                    ->state(fn (GalleryEvent $record): string => self::statoDellAnalisi($record))
                    ->badge()
                    ->formatStateUsing(fn (string $state, GalleryEvent $record): string => self::avanzamentoDellAnalisi($state, $record))
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success',
                        'partial' => 'warning',
                        'none' => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'complete' => 'Tutte le foto sono state analizzate con AI',
                        'partial' => 'Alcune foto non sono ancora state analizzate',
                        'none' => 'Nessuna foto analizzata con AI',
                        default => 'Nessuna foto presente',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('analyzeEvent')
                    ->label('Analizza AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Tutte le foto di questo evento verranno analizzate con AI in background.')
                    ->action(function (GalleryEvent $record, $livewire) {
                        // Query efficiente: evita N+1 con whereHas invece di filter+hasMedia
                        $images = GalleryImage::where('gallery_event_id', $record->id)
                            ->whereHas('media')
                            ->get();

                        if ($images->isEmpty()) {
                            Notification::make()
                                ->title('Nessuna foto trovata')
                                ->body('Questo evento non ha foto da analizzare.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $jobs = $images->map(fn ($image) => new AnalyzeGalleryImageJob($image))->toArray();

                        $batch = Bus::batch($jobs)
                            ->name('Analisi AI Evento: '.$record->title)
                            ->onQueue('ai')
                            ->allowFailures()
                            ->dispatch();

                        // Cache key scoped per utente
                        Cache::put('gallery_ai_batch_id_user_'.auth()->id(), $batch->id, now()->addHours(6));

                        // Notifica il frontend di avviare il polling
                        $livewire->dispatch('batch-started');

                        Notification::make()
                            ->title('Analisi AI avviata')
                            ->body($images->count().' foto in fase di analisi.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Attenzione: verranno eliminate anche tutte le foto associate a questo evento. L\'operazione è irreversibile.')
                    ->before(function (GalleryEvent $record) {
                        $record->loadMissing('galleryImages');
                        foreach ($record->galleryImages as $image) {
                            $image->clearMediaCollection('gallery');
                            $image->delete();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            foreach ($records as $event) {
                                $event->loadMissing('galleryImages');
                                foreach ($event->galleryImages as $image) {
                                    $image->clearMediaCollection('gallery');
                                    $image->delete();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('event_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            GalleryImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleryEvents::route('/'),
            'create' => CreateGalleryEvent::route('/create'),
            'edit' => EditGalleryEvent::route('/{record}/edit'),
        ];
    }
}
