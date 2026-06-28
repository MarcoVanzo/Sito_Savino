<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryImageResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\GalleryImage;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;

class GalleryImageResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = GalleryImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Gestione Gallery (AI Tagging)';

    protected static ?string $modelLabel = 'Immagine Galleria';

    protected static ?string $pluralModelLabel = 'Galleria Fotografica';

    protected static ?string $navigationGroup = 'Sito Web';

    protected static ?int $navigationSort = 4;

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
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Player $record) => "{$record->first_name} {$record->last_name}"),
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('analyze')
                    ->label('Analizza AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->action(function (GalleryImage $record) {
                        \App\Jobs\AnalyzeGalleryImageJob::dispatchSync($record);
                        \Filament\Notifications\Notification::make()
                            ->title('Analisi completata')
                            ->success()
                            ->send();
                    })
            ])
            ->bulkActions(array_merge(static::standardBulkActions(), [
                Tables\Actions\BulkAction::make('analyzeBulk')
                    ->label('Analizza con AI (Bulk)')
                    ->icon('heroicon-o-sparkles')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        foreach ($records as $record) {
                            \App\Jobs\AnalyzeGalleryImageJob::dispatch($record);
                        }
                        \Filament\Notifications\Notification::make()
                            ->title('Analisi avviata')
                            ->body('Le foto selezionate verranno analizzate in background.')
                            ->success()
                            ->send();
                    })
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
