<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoResource\Pages\CreateEvento;
use App\Filament\Resources\EventoResource\Pages\EditEvento;
use App\Filament\Resources\EventoResource\Pages\ListEventi;
use App\Models\Evento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 46;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Evento')
                    ->description('La homepage mostra i prossimi tre eventi pubblicati; un evento sparisce da solo quando è finito.')
                    ->schema([
                        TextInput::make('titolo')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('descrizione')
                            ->label('Descrizione breve')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        DateTimePicker::make('inizia_il')
                            ->label('Inizio')
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('finisce_il')
                            ->label('Fine')
                            ->seconds(false)
                            ->after('inizia_il')
                            ->helperText('Facoltativa: senza, l\'evento resta in homepage fino a fine giornata.'),
                        TextInput::make('luogo')
                            ->label('Luogo')
                            ->maxLength(255),
                        // Niente ->url(): spesso è un percorso interno (/biglietteria).
                        TextInput::make('link')
                            ->label('Link')
                            ->helperText('Percorso interno (es. /biglietteria) o indirizzo completo.')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Copertina')
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

                Toggle::make('pubblicato')
                    ->label('Pubblicato')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('copertina')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Immagine')
                    ->collection(Evento::COLLEZIONE_COPERTINA),
                TextColumn::make('titolo')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('inizia_il')
                    ->label('Inizio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('luogo')
                    ->label('Luogo')
                    ->limit(30),
                ToggleColumn::make('pubblicato')
                    ->label('Pubblicato'),
            ])
            ->defaultSort('inizia_il', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventi::route('/'),
            'create' => CreateEvento::route('/create'),
            'edit' => EditEvento::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }
}
