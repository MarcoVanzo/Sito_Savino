<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryImageResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryImage;
use App\Models\Player;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class GalleryImageResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = GalleryImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Foto Gallery';

    protected static ?string $modelLabel = 'Immagine Galleria';

    protected static ?string $pluralModelLabel = 'Galleria Fotografica';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'gallery';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Immagine e Dettagli')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Immagine')
                            ->collection('gallery')
                            ->image()
                            ->maxSize(5120)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('title')
                            ->label('Titolo / Testo Alternativo')
                            ->maxLength(255)
                            ->helperText('Breve descrizione dell\'immagine, utile per SEO e accessibilità.'),
                        Forms\Components\TextInput::make('category')
                            ->label('Categoria')
                            ->required()
                            ->maxLength(255)
                            ->default('Partite')
                            ->datalist([
                                'Partite',
                                'Allenamenti',
                                'Eventi',
                                'Tifosi',
                                'Backstage',
                            ]),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true)
                            ->required(),
                        Forms\Components\Select::make('players')
                            ->label('Atlete Taggate')
                            ->multiple()
                            ->relationship('players', 'last_name')
                            ->preload()
                            ->searchable(['first_name', 'last_name'])
                            ->getOptionLabelFromRecordUsing(fn (Player $record) => "{$record->first_name} {$record->last_name}"),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label('Immagine')
                    ->collection('gallery')
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('players.last_name')
                    ->label('Atlete')
                    ->badge()
                    ->searchable()
                    ->limitList(3),
                Tables\Columns\IconColumn::make('needs_review')
                    ->label('Da Rev.')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(function () {
                        return GalleryImage::query()
                            ->select('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray();
                    }),
                Tables\Filters\SelectFilter::make('gallery_event_id')
                    ->label('Evento')
                    ->relationship('galleryEvent', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('needs_review')
                    ->label('Da Revisionare')
                    ->placeholder('Tutte le foto')
                    ->trueLabel('Sì, da identificare')
                    ->falseLabel('No, OK'),
            ])
            ->actions([
                Tables\Actions\Action::make('identifica')
                    ->label('Identifica')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn (GalleryImage $record) => $record->needs_review)
                    ->mountUsing(function (Form $form, GalleryImage $record) {
                        $form->fill([
                            'players' => $record->players->pluck('id')->toArray(),
                        ]);
                    })
                    ->form([
                        Forms\Components\Select::make('players')
                            ->label('Atlete presenti')
                            ->multiple()
                            ->options(Player::all()->pluck('last_name', 'id'))
                            ->searchable(),
                    ])
                    ->action(function (GalleryImage $record, array $data) {
                        $record->players()->sync($data['players'] ?? []);
                        $record->needs_review = false;
                        $record->save();
                        Notification::make()->title('Identificata con successo')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('analyze')
                    ->label('Analizza AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->action(function (GalleryImage $record) {
                        AnalyzeGalleryImageJob::dispatchSync($record);
                        Notification::make()
                            ->title('Analisi completata')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions(array_merge(static::standardBulkActions(), [
                Tables\Actions\BulkAction::make('analyzeBulk')
                    ->label('Analizza con AI (Bulk)')
                    ->icon('heroicon-o-sparkles')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            AnalyzeGalleryImageJob::dispatch($record);
                        }
                        Notification::make()
                            ->title('Analisi avviata')
                            ->body('Le foto selezionate verranno analizzate in background.')
                            ->success()
                            ->send();
                    }),
            ]))
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleryImages::route('/'),
            'create' => Pages\CreateGalleryImage::route('/create'),
            'edit' => Pages\EditGalleryImage::route('/{record}/edit'),
        ];
    }
}
