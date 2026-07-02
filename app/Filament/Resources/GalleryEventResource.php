<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryEventResource\Pages;
use App\Filament\Resources\GalleryEventResource\RelationManagers;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Services\GalleryUploadService;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class GalleryEventResource extends Resource
{
    protected static ?string $model = GalleryEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Foto Gallery';

    protected static ?string $modelLabel = 'Evento Galleria';

    protected static ?string $pluralModelLabel = 'Eventi Galleria';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'gallery';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Evento')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('event_date')
                            ->label('Data Evento'),
                        Forms\Components\Select::make('category')
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
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Caricamento Foto')
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'galleryImages as ai_analyzed_images_count' => fn ($q) => $q->whereNotNull('ai_analyzed_at'),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->label('Data Evento')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gallery_images_count')
                    ->label('Numero Foto')
                    ->counts('galleryImages'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ai_status')
                    ->label('Analisi AI')
                    ->state(function (GalleryEvent $record): string {
                        $total = $record->gallery_images_count ?? 0;
                        if ($total === 0) {
                            return 'empty';
                        }
                        $analyzed = $record->ai_analyzed_images_count ?? 0;
                        if ($analyzed === $total) {
                            return 'complete';
                        }
                        if ($analyzed > 0) {
                            return 'partial';
                        }

                        return 'none';
                    })
                    ->badge()
                    ->formatStateUsing(function (string $state, GalleryEvent $record): string {
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
                    })
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('analyzeEvent')
                    ->label('Analizza AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Tutte le foto di questo evento verranno analizzate con AI in background.')
                    ->action(function (GalleryEvent $record, Action $action, $livewire) {
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
                            ->name("Analisi AI — {$record->title}")
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
                Tables\Actions\DeleteAction::make()
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
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
            RelationManagers\GalleryImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleryEvents::route('/'),
            'create' => Pages\CreateGalleryEvent::route('/create'),
            'edit' => Pages\EditGalleryEvent::route('/{record}/edit'),
        ];
    }
}
