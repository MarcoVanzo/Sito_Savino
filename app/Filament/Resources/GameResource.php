<?php

namespace App\Filament\Resources;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Filament\Resources\GameResource\Pages;
use App\Filament\Resources\GameResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GameResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Game::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'competition_type';

    protected static ?string $modelLabel = 'Partita';

    protected static ?string $pluralModelLabel = 'Partite';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    // La classifica ha ora una voce propria (StandingResource): qui restano
    // calendario e risultati, altrimenti due voci si chiamerebbero "Classifica".
    protected static ?string $navigationLabel = 'Calendario e Risultati';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'partite';

    /**
     * Titolo del record (intestazione della scheda, breadcrumb, ricerca
     * globale). Serve l'override perché `competition_type` è castato a enum e
     * il valore grezzo non è una stringa: restituirlo così com'è fa fallire
     * il render della pagina.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof Game) {
            return null;
        }

        return ($record->homeTeam?->name ?? '?').' - '.($record->awayTeam?->name ?? '?');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Partita')
                    ->schema([
                        Forms\Components\Select::make('season_id')
                            ->label('Stagione')
                            ->relationship('season', 'name')
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('competition_type')
                            ->label('Competizione')
                            ->options(CompetitionType::class)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Stato')
                            ->options(GameStatus::class)
                            ->default(GameStatus::Scheduled)
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Squadre e Risultato')
                    ->schema([
                        Forms\Components\Select::make('home_team_id')
                            ->label('Squadra in Casa')
                            ->relationship('homeTeam', 'name')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('home_score')
                            ->label('Punti (Casa)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Select::make('away_team_id')
                            ->label('Squadra in Trasferta')
                            ->relationship('awayTeam', 'name')
                            ->required()
                            ->different('home_team_id'),
                        Forms\Components\TextInput::make('away_score')
                            ->label('Punti (Trasferta)')
                            ->numeric()
                            ->minValue(0),
                    ])->columns(2),
                Forms\Components\Section::make('Programmazione')
                    ->schema([
                        Forms\Components\DateTimePicker::make('match_date')
                            ->label('Data e Ora Incontro')
                            ->required(),
                        Forms\Components\TextInput::make('location')
                            ->label('Luogo / Palazzetto')
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('match_date')
                    ->label('Data e Ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->location),
                Tables\Columns\TextColumn::make('competition_type')
                    ->label('Competizione')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof GameStatus ? $state : GameStatus::tryFrom((string) $state)) {
                        GameStatus::Scheduled => 'gray',
                        GameStatus::InProgress => 'danger',
                        GameStatus::Completed => 'success',
                        GameStatus::Postponed => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('homeTeam.name')
                    ->label('Casa')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('home_score')
                    ->label('Pt.')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('away_score')
                    ->label('Pt.')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('awayTeam.name')
                    ->label('Trasferta')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('matchday')
                    ->label('Giornata')
                    ->alignCenter()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Segnala dove il tabellino è già stato importato: è il motivo
                // per cui alcune schede gara hanno la tab "Tabellino" e altre no.
                Tables\Columns\IconColumn::make('stats_synced_at')
                    ->label('Tabellino')
                    ->alignCenter()
                    ->boolean()
                    ->tooltip(fn ($record) => $record->stats_synced_at
                        ? 'Tabellino importato il '.$record->stats_synced_at->format('d/m/Y H:i')
                        : 'Tabellino non ancora importato'),
            ])
            ->defaultSort('match_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('competition_type')
                    ->label('Competizione')
                    ->options(CompetitionType::class),
                Tables\Filters\SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name'),
            ])
            ->actions(static::viewAndEditActions())
            ->bulkActions(static::standardBulkActions());
    }

    /**
     * Scheda della partita: dati di referto importati dalla Lega, in sola
     * lettura. Il tabellino per giocatrice sta invece nel RelationManager
     * (vedi getRelations), che gestisce da solo ordinamento e raggruppamento.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Partita')
                    ->schema([
                        Infolists\Components\TextEntry::make('match_date')
                            ->label('Data e ora')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('competition_type')
                            ->label('Competizione')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Stato')
                            ->badge(),
                        Infolists\Components\TextEntry::make('matchday')
                            ->label('Giornata')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('phase')
                            ->label('Fase')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('season.name')
                            ->label('Stagione'),
                        Infolists\Components\TextEntry::make('location')
                            ->label('Palazzetto')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make('Risultato')
                    ->schema([
                        Infolists\Components\ImageEntry::make('home_logo')
                            ->label('')
                            ->checkFileExistence(false)
                            ->height(40)
                            ->state(fn (Game $record) => $record->homeTeam?->logoUrl()),
                        Infolists\Components\TextEntry::make('homeTeam.name')
                            ->label('Casa')
                            ->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('score')
                            ->label('Set')
                            ->weight(FontWeight::Bold)
                            ->state(fn (Game $record) => $record->home_score !== null || $record->away_score !== null
                                ? ($record->home_score ?? 0).' - '.($record->away_score ?? 0)
                                : '—'),
                        Infolists\Components\TextEntry::make('awayTeam.name')
                            ->label('Trasferta')
                            ->weight(FontWeight::Bold),
                        Infolists\Components\ImageEntry::make('away_logo')
                            ->label('')
                            ->checkFileExistence(false)
                            ->height(40)
                            ->state(fn (Game $record) => $record->awayTeam?->logoUrl()),
                    ])->columns(5),

                Infolists\Components\Section::make('Referto')
                    ->description('Dati del referto pubblicato dalla Lega: non sono modificabili dal CMS.')
                    ->schema([
                        Infolists\Components\TextEntry::make('spectators')
                            ->label('Spettatori')
                            ->numeric()
                            ->placeholder('non comunicati'),
                        Infolists\Components\TextEntry::make('referees')
                            ->label('Arbitri')
                            ->placeholder('non comunicati')
                            ->columnSpan(2),
                        Infolists\Components\TextEntry::make('total_duration')
                            ->label('Durata totale')
                            ->placeholder('—')
                            ->state(function (Game $record): ?string {
                                $minutes = collect($record->set_scores ?? [])
                                    ->sum(fn (array $set) => (int) ($set['duration'] ?? 0));

                                return $minutes > 0 ? $minutes.' min' : null;
                            }),
                        Infolists\Components\RepeatableEntry::make('set_scores')
                            ->label('Parziali per set')
                            ->schema([
                                Infolists\Components\TextEntry::make('set')
                                    ->label('Set')
                                    ->formatStateUsing(fn ($state) => $state.'°'),
                                Infolists\Components\TextEntry::make('duration')
                                    ->label('Durata')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state) => $state !== null ? $state.' min' : null),
                                Infolists\Components\TextEntry::make('partials')
                                    ->label('Parziali (l\'ultimo è il punteggio del set)')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(' · ', $state) : $state),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->visible(fn (Game $record) => filled($record->set_scores)),
                    ])
                    ->columns(4)
                    ->visible(fn (Game $record) => $record->isImported()
                        || filled($record->set_scores)
                        || filled($record->spectators)
                        || filled($record->referees)),

                Infolists\Components\Section::make('Sincronizzazione Lega')
                    ->schema([
                        Infolists\Components\TextEntry::make('lvf_match_id')
                            ->label('ID gara Lega'),
                        Infolists\Components\TextEntry::make('lvf_synced_at')
                            ->label('Gara sincronizzata il')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('mai'),
                        Infolists\Components\TextEntry::make('stats_synced_at')
                            ->label('Tabellino sincronizzato il')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('mai'),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (Game $record) => $record->isImported()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PlayerStatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'view' => Pages\ViewGame::route('/{record}'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['homeTeam.media', 'awayTeam.media', 'season']);
    }
}
