<?php

namespace App\Filament\Resources;

use App\Enums\PlayerPosition;
use App\Filament\Actions\SyncFaceAction;
use App\Filament\Clusters\SerieA1;
use App\Filament\Columns\AiScoreColumn;
use App\Filament\Forms\CampoAtleta;
use App\Filament\Resources\RosterResource\Pages\CreateRoster;
use App\Filament\Resources\RosterResource\Pages\EditRoster;
use App\Filament\Resources\RosterResource\Pages\ListRosters;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Roster;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RosterResource extends Resource
{
    use HasStandardTableActions;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $model = Roster::class;

    /**
     * Titolo del record nelle modali Vedi/Modifica.
     * Non si può usare $recordTitleAttribute con dot-notation perché
     * Eloquent::getAttributeValue() non risolve le relazioni.
     */
    public static function getRecordTitle(?Model $record): Htmlable|string|null
    {
        return $record?->player->last_name ?? "Roster #{$record?->id}";
    }

    protected static bool $isGloballySearchable = false;

    protected static ?string $modelLabel = 'Giocatore in Rosa';

    protected static ?string $pluralModelLabel = 'Giocatori in Rosa (Roster)';

    protected static ?string $cluster = SerieA1::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Atlete';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'roster';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Associazione Stagionale')
                    ->schema([
                        CampoAtleta::make()
                            ->required(),
                        Select::make('team_id')
                            ->label('Squadra')
                            ->relationship(
                                'team',
                                'name',
                                fn (Builder $query) => $query->whereNotIn('category', ['B1', 'U17', 'U15'])
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('season_id')
                            ->label('Stagione')
                            ->relationship('season', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(3),
                Section::make('Dettagli Tecnici')
                    ->schema([
                        TextInput::make('jersey_number')
                            ->label('Numero di Maglia')
                            ->numeric()
                            ->minValue(0),
                        Select::make('role')
                            ->label('Ruolo in Campo')
                            ->options(PlayerPosition::class)
                            ->required(),
                        TextInput::make('height_cm')
                            ->label('Altezza (cm)')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('is_captain')
                            ->label('Capitano della Squadra')
                            ->required(),
                    ])->columns(2),
                Section::make('Biografia e Foto')
                    ->schema([
                        Textarea::make('bio')
                            ->label('Biografia Stagionale')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('official_photo')
                            ->label('Foto Ufficiale (Roster)')
                            ->collection('rosters_official')
                            ->disk('s3')
                            ->image()
                            ->visibility('public')
                            ->openable()
                            ->fetchFileInformation(false)
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false),
                        SpatieMediaLibraryFileUpload::make('action_photos')
                            ->label('Foto in Azione')
                            ->collection('rosters_action')
                            ->disk('s3')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->visibility('public')
                            ->openable()
                            ->fetchFileInformation(false)
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->helperText('Puoi caricare più foto contemporaneamente.'),
                    ])->columns(2),
                Section::make('Stato Addestramento AI')
                    ->description('Mostra quali foto sono state accettate o scartate dall\'AI per il riconoscimento facciale.')
                    ->icon('heroicon-o-cpu-chip')
                    ->collapsed()
                    ->schema([
                        ViewField::make('ai_photo_status')
                            ->label('')
                            ->dehydrated(false)
                            ->view('filament.components.ai-photo-status'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('official_photo')
                    ->conversion('thumb')
                    ->label('')
                    ->collection('rosters_official')
                    ->circular()
                    ->checkFileExistence(false),
                TextColumn::make('player.last_name')
                    ->label('Atleta')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('team.name')
                    ->label('Squadra')
                    ->sortable(),
                TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                TextColumn::make('jersey_number')
                    ->label('Maglia'),
                TextColumn::make('role')
                    ->label('Ruolo'),
                IconColumn::make('is_captain')
                    ->label('Capitano')
                    ->boolean(),
                AiScoreColumn::make(),
            ])
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name'),
            ])
            ->recordActions(array_merge([
                SyncFaceAction::make('syncFace'),
                // Il palmarès sta sull'anagrafica dell'atleta, non sulla riga
                // di rosa: segue l'atleta di stagione in stagione, e duplicarlo
                // per ogni annata significherebbe reimportarlo ogni anno.
                Action::make('palmares')
                    ->label('Palmarès')
                    ->icon('heroicon-o-trophy')
                    ->color('warning')
                    ->url(fn (Roster $record): string => PlayerResource::getUrl('edit', ['record' => $record->player_id])),
            ], static::viewAndEditActions()))
            ->toolbarActions(static::standardBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRosters::route('/'),
            'create' => CreateRoster::route('/create'),
            'edit' => EditRoster::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['player', 'team', 'season', 'media'])
            ->whereHas('team', fn (Builder $q) => $q->whereNotIn('category', ['B1', 'U17', 'U15']));
    }
}
