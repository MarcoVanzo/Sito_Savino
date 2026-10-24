<?php

namespace App\Filament\Resources;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Filament\Resources\GameResource\Pages\CreateGame;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\Pages\ListGames;
use App\Filament\Resources\GameResource\Pages\ViewGame;
use App\Filament\Resources\GameResource\RelationManagers\PlayerStatsRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Game;
use App\Models\Team;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GameResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Game::class;

    /** Formato con cui data e ora della gara compaiono nel pannello. */
    private const DATE_TIME_FORMAT = 'd/m/Y H:i';

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'competition_type';

    protected static ?string $modelLabel = 'Partita';

    protected static ?string $pluralModelLabel = 'Partite';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    // La classifica ha ora una voce propria (StandingResource): qui restano
    // calendario e risultati, altrimenti due voci si chiamerebbero "Classifica".
    protected static ?string $navigationLabel = 'Calendario e Risultati';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

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

        return ($record->homeTeam->name ?? '?').' - '.($record->awayTeam->name ?? '?');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Le gare importate si riallineano a ogni sincronizzazione: chi
                // le modifica qui deve saperlo prima di salvare, non scoprirlo
                // un'ora dopo quando la modifica è sparita.
                Section::make('Gara importata')
                    ->schema([
                        Placeholder::make('avviso_sincronizzazione')
                            ->hiddenLabel()
                            ->content(fn (?Game $record): string => 'Data, squadre, luogo e risultato arrivano dalla '
                                .$record?->fonteDellaSincronizzazione()
                                .' e vengono riallineati a ogni sincronizzazione: qui si modificano solo il link della diretta e i biglietti.'),
                    ])
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (?Game $record): bool => (bool) $record?->isImported()),
                Section::make('Dettagli Partita')
                    ->schema([
                        Select::make('season_id')
                            ->label('Stagione')
                            ->relationship('season', 'name')
                            ->preload()
                            ->required(),
                        Select::make('competition_type')
                            ->label('Competizione')
                            ->options(CompetitionType::class)
                            ->required(),
                        Select::make('status')
                            ->label('Stato')
                            ->options(GameStatus::class)
                            ->default(GameStatus::Scheduled)
                            ->required(),
                    ])->columns(2),
                Section::make('Squadre e Risultato')
                    ->schema([
                        Select::make('home_team_id')
                            ->label('Squadra in Casa')
                            ->relationship('homeTeam', 'name')
                            ->required()
                            ->live(),
                        TextInput::make('home_score')
                            ->label('Set vinti (Casa)')
                            ->numeric()
                            ->minValue(0),
                        Select::make('away_team_id')
                            ->label('Squadra in Trasferta')
                            ->relationship('awayTeam', 'name')
                            ->required()
                            ->different('home_team_id'),
                        TextInput::make('away_score')
                            ->label('Set vinti (Trasferta)')
                            ->numeric()
                            ->minValue(0),
                    ])->columns(2),
                Section::make('Programmazione')
                    ->schema([
                        DateTimePicker::make('match_date')
                            ->label('Data e Ora Incontro')
                            ->required(),
                        TextInput::make('location')
                            ->label('Luogo / Palazzetto')
                            ->maxLength(255),
                        TextInput::make('stream_url')
                            ->label('Link diretta streaming')
                            ->url()
                            // Il link finisce in un `href` (e, se incorporabile,
                            // in un `src`): solo indirizzi web, `javascript:`
                            // sarebbe codice eseguito nella pagina.
                            ->rule('url:http,https')
                            ->maxLength(500)
                            ->placeholder('es. https://www.youtube.com/watch?v=...')
                            ->helperText('Con un link YouTube, Vimeo, Twitch o Dailymotion la diretta si apre in una finestra dentro al sito. Altri indirizzi vengono aperti in una scheda nuova.')
                            ->columnSpanFull(),
                    ])->columns(2),
                // Finiscono solo nei dati strutturati della gara (l'offerta di
                // SportsEvent per Google): niente si inventa, un campo vuoto
                // resta fuori.
                Section::make('Biglietti')
                    ->description('Per i risultati di Google. Vuoti: la prossima gara in casa usa il link del Match Day o della Biglietteria, le altre non dichiarano biglietti.')
                    ->schema([
                        TextInput::make('biglietti_url')
                            ->label('Link di vendita (Vivaticket)')
                            ->url()
                            ->rule('url:https')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        DateTimePicker::make('biglietti_in_vendita_dal')
                            ->label('In vendita dal'),
                        TextInput::make('biglietti_prezzo_da')
                            ->label('Prezzo più basso (€)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Solo se è quello pubblicato su Vivaticket.'),
                        Toggle::make('biglietti_esauriti')
                            ->label('Esauriti')
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->visible(fn (Get $get): bool => (bool) Team::find($get('home_team_id'))?->is_internal),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('match_date')
                    ->label('Data e Ora')
                    ->dateTime(self::DATE_TIME_FORMAT)
                    ->sortable()
                    ->description(fn ($record) => $record->location),
                TextColumn::make('competition_type')
                    ->label('Competizione')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof GameStatus ? $state : GameStatus::tryFrom((string) $state)) {
                        GameStatus::Scheduled => 'gray',
                        GameStatus::InProgress => 'danger',
                        GameStatus::Completed => 'success',
                        GameStatus::Postponed => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('homeTeam.name')
                    ->label('Casa')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('home_score')
                    ->label('Set')
                    ->alignCenter(),
                TextColumn::make('away_score')
                    ->label('Set')
                    ->alignCenter(),
                TextColumn::make('awayTeam.name')
                    ->label('Trasferta')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('matchday')
                    ->label('Giornata')
                    ->alignCenter()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Segnala dove il tabellino è già stato importato: è il motivo
                // per cui alcune schede gara hanno la tab "Tabellino" e altre no.
                IconColumn::make('stats_synced_at')
                    ->label('Tabellino')
                    ->alignCenter()
                    ->boolean()
                    ->tooltip(fn ($record) => $record->stats_synced_at
                        ? 'Tabellino importato il '.$record->stats_synced_at->format(self::DATE_TIME_FORMAT)
                        : 'Tabellino non ancora importato'),
            ])
            ->defaultSort('match_date', 'desc')
            ->filters([
                SelectFilter::make('competition_type')
                    ->label('Competizione')
                    ->options(CompetitionType::class),
                SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name'),
            ])
            ->recordActions(static::viewAndEditActions())
            ->toolbarActions(static::standardBulkActions());
    }

    /**
     * Scheda della partita: dati di referto importati dalla Lega, in sola
     * lettura. Il tabellino per giocatrice sta invece nel RelationManager
     * (vedi getRelations), che gestisce da solo ordinamento e raggruppamento.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Partita')
                    ->schema([
                        TextEntry::make('match_date')
                            ->label('Data e ora')
                            ->dateTime(self::DATE_TIME_FORMAT),
                        TextEntry::make('competition_type')
                            ->label('Competizione')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Stato')
                            ->badge(),
                        TextEntry::make('matchday')
                            ->label('Giornata')
                            ->placeholder('—'),
                        TextEntry::make('phase')
                            ->label('Fase')
                            ->placeholder('—'),
                        TextEntry::make('season.name')
                            ->label('Stagione'),
                        TextEntry::make('location')
                            ->label('Palazzetto')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('stream_url')
                            ->label('Diretta streaming')
                            ->placeholder('—')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('Risultato')
                    ->schema([
                        ImageEntry::make('home_logo')
                            ->label('')
                            ->checkFileExistence(false)
                            ->height(40)
                            ->state(fn (Game $record) => $record->homeTeam?->logoUrl()),
                        TextEntry::make('homeTeam.name')
                            ->label('Casa')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('score')
                            ->label('Set')
                            ->weight(FontWeight::Bold)
                            ->state(fn (Game $record) => $record->home_score !== null || $record->away_score !== null
                                ? ($record->home_score ?? 0).' - '.($record->away_score ?? 0)
                                : '—'),
                        TextEntry::make('awayTeam.name')
                            ->label('Trasferta')
                            ->weight(FontWeight::Bold),
                        ImageEntry::make('away_logo')
                            ->label('')
                            ->checkFileExistence(false)
                            ->height(40)
                            ->state(fn (Game $record) => $record->awayTeam?->logoUrl()),
                    ])->columns(5),

                Section::make('Referto')
                    ->description('Dati del referto pubblicato dalla Lega: non sono modificabili dal CMS.')
                    ->schema([
                        TextEntry::make('spectators')
                            ->label('Spettatori')
                            ->numeric()
                            ->placeholder('non comunicati'),
                        TextEntry::make('referees')
                            ->label('Arbitri')
                            ->placeholder('non comunicati')
                            ->columnSpan(2),
                        TextEntry::make('total_duration')
                            ->label('Durata totale')
                            ->placeholder('—')
                            ->state(function (Game $record): ?string {
                                $minutes = collect($record->set_scores ?? [])
                                    ->sum(fn (array $set) => (int) ($set['duration'] ?? 0));

                                return $minutes > 0 ? $minutes.' min' : null;
                            }),
                        RepeatableEntry::make('set_scores')
                            ->label('Parziali per set')
                            ->schema([
                                TextEntry::make('set')
                                    ->label('Set')
                                    ->formatStateUsing(fn ($state) => $state.'°'),
                                TextEntry::make('duration')
                                    ->label('Durata')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state) => $state !== null ? $state.' min' : null),
                                TextEntry::make('partials')
                                    ->label('Parziali (l\'ultimo è il punteggio del set)')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(' · ', $state) : $state),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->visible(fn (Game $record) => filled($record->set_scores)),
                    ])
                    ->columns(4)
                    ->visible(fn (Game $record) => $record->importataDallaLega()
                        || filled($record->set_scores)
                        || filled($record->spectators)
                        || filled($record->referees)),

                Section::make('Sincronizzazione Lega')
                    ->schema([
                        TextEntry::make('lvf_match_id')
                            ->label('ID gara Lega'),
                        TextEntry::make('lvf_synced_at')
                            ->label('Gara sincronizzata il')
                            ->dateTime(self::DATE_TIME_FORMAT)
                            ->placeholder('mai'),
                        TextEntry::make('stats_synced_at')
                            ->label('Tabellino sincronizzato il')
                            ->dateTime(self::DATE_TIME_FORMAT)
                            ->placeholder('mai'),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (Game $record) => $record->importataDallaLega()),

                Section::make('Sincronizzazione CEV')
                    ->description('Gara importata dal portale della CEV con `cev:sync`: data, squadre, luogo e risultato si riallineano a ogni giro.')
                    ->schema([
                        TextEntry::make('cev_match_id')
                            ->label('ID gara CEV')
                            ->url(fn (Game $record) => $record->indirizzoSulPortaleCev())
                            ->openUrlInNewTab(),
                        TextEntry::make('updated_at')
                            ->label('Ultima modifica')
                            ->dateTime(self::DATE_TIME_FORMAT),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (Game $record) => $record->importataDallaCev()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PlayerStatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGames::route('/'),
            'create' => CreateGame::route('/create'),
            'view' => ViewGame::route('/{record}'),
            'edit' => EditGame::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['homeTeam.media', 'awayTeam.media', 'season']);
    }
}
