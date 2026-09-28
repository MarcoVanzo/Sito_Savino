<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoResource\Pages;
use App\Models\Evento;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo spazio «Eventi» della homepage: la sezione compare solo quando c'è almeno
 * un evento pubblicato non ancora finito.
 */
class EventoResource extends Resource
{
    use Translatable;

    protected static ?string $model = Evento::class;

    protected static ?string $recordTitleAttribute = 'titolo';

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventi in homepage';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 46;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Evento')
                    ->description('La homepage mostra i prossimi tre eventi pubblicati; un evento sparisce da solo quando è finito.')
                    ->schema([
                        Forms\Components\TextInput::make('titolo')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('descrizione')
                            ->label('Descrizione breve')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('inizia_il')
                            ->label('Inizio')
                            ->seconds(false)
                            ->required(),
                        Forms\Components\DateTimePicker::make('finisce_il')
                            ->label('Fine')
                            ->seconds(false)
                            ->after('inizia_il')
                            ->helperText('Facoltativa: senza, l\'evento resta in homepage fino a fine giornata.'),
                        Forms\Components\TextInput::make('luogo')
                            ->label('Luogo')
                            ->maxLength(255),
                        // Niente ->url(): spesso è un percorso interno (/biglietteria).
                        Forms\Components\TextInput::make('link')
                            ->label('Link')
                            ->helperText('Percorso interno (es. /biglietteria) o indirizzo completo.')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Copertina')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('copertina')
                            ->label('Immagine')
                            ->collection(Evento::COLLEZIONE_COPERTINA)
                            ->image()
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1600')
                            ->imageResizeUpscale(false)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Toggle::make('pubblicato')
                    ->label('Pubblicato')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('copertina')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Immagine')
                    ->collection(Evento::COLLEZIONE_COPERTINA),
                Tables\Columns\TextColumn::make('titolo')
                    ->label('Titolo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inizia_il')
                    ->label('Inizio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('luogo')
                    ->label('Luogo')
                    ->limit(30),
                Tables\Columns\ToggleColumn::make('pubblicato')
                    ->label('Pubblicato'),
            ])
            ->defaultSort('inizia_il', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventi::route('/'),
            'create' => Pages\CreateEvento::route('/create'),
            'edit' => Pages\EditEvento::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }
}
