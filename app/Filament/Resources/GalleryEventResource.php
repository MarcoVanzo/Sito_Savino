<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryEventResource\Pages;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Storage;

class GalleryEventResource extends Resource
{
    protected static ?string $model = GalleryEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Eventi Galleria';

    protected static ?string $modelLabel = 'Evento Galleria';

    protected static ?string $pluralModelLabel = 'Eventi Galleria';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'gallery-events';

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
                            ->maxSize(5120)
                            ->directory('temp_gallery_uploads')
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function (FileUpload $component, $state, GalleryEvent $record) {
                                if (!is_array($state)) return;
                                
                                foreach ($state as $file) {
                                    $image = new GalleryImage();
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
                                    
                                    \App\Jobs\AnalyzeGalleryImageJob::dispatch($image);
                                }
                                
                                // Clear the state so they aren't processed again on next save
                                $component->state([]);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_photos')
                    ->label('Vedi Foto')
                    ->icon('heroicon-o-photo')
                    ->url(fn (GalleryEvent $record): string => GalleryImageResource::getUrl('index', ['tableFilters' => ['gallery_event_id' => ['value' => $record->id]]])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('event_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Potremmo aggiungere una RelationManager per galleryImages in futuro
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
