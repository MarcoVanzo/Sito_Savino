<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerStatResource\Pages\CreatePlayerStat;
use App\Filament\Resources\PlayerStatResource\Pages\EditPlayerStat;
use App\Filament\Resources\PlayerStatResource\Pages\ListPlayerStats;
use App\Filament\Resources\PlayerStatResource\Pages\ViewPlayerStat;
use App\Models\PlayerStat;
use App\Models\Season;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Totali di stagione di un'atleta in una squadra.
 *
 * Due chiarimenti indispensabili per leggere questo file.
 *
 * 1. `attacks` è il numero di attacchi TENTATI, non i punti realizzati: quelli
 *    stanno in `attack_points`. Prima dell'arricchimento della tabella il campo
 *    conteneva i punti ed era etichettato "Attacchi", quindi ogni etichetta va
 *    letta con attenzione.
 *
 * 2. Le righe hanno due origini diverse e vanno trattate in modo diverso:
 *    - importate dalla Lega, riconoscibili da `last_synced_at` valorizzata.
 *      `LvfStatsSyncService::rebuildSeasonTotals()` le RICOSTRUISCE da capo a
 *      ogni sincronizzazione, quindi renderle modificabili prometterebbe una
 *      persistenza che non esiste: sono di sola lettura;
 *    - inserite a mano, con `last_synced_at` a null. Servono per le squadre
 *      giovanili, che giocano campionati di cui la Lega non pubblica alcun
 *      tabellino: per quelle il CMS è l'unica fonte possibile. La ricostruzione
 *      lavora solo sulle gare con `lvf_match_id` e usa `updateOrCreate`, quindi
 *      non tocca né cancella queste righe, che restano modificabili.
 *
 * Il discrimine è quindi `last_synced_at` e va tenuto pulito: il form NON lo
 * espone, altrimenti basterebbe compilarlo per rendere una riga manuale
 * indistinguibile da una importata (e quindi non più modificabile).
 */
class PlayerStatResource extends Resource
{
    protected static ?string $model = PlayerStat::class;

    /** Etichette condivise fra form, tabella e scheda della statistica. */
    private const LABEL_MATCHES_PLAYED = 'Partite giocate';

    private const LABEL_SETS_PLAYED = 'Set giocati';

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $isGloballySearchable = false;

    // Il nome precedente ("Statistica Partita") era fuorviante: le statistiche
    // della singola gara vivono in `game_player_stats`, qui ci sono i totali.
    protected static ?string $modelLabel = 'Statistica di stagione';

    protected static ?string $pluralModelLabel = 'Statistiche atlete';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?string $navigationLabel = 'Statistiche atlete';

    protected static ?int $navigationSort = 9;

    /**
     * Una riga è modificabile solo se non arriva dalla sincronizzazione.
     */
    public static function isManual(?Model $record): bool
    {
        return $record instanceof PlayerStat && $record->last_synced_at === null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Riga')
                    ->description(
                        'Inserimento manuale: da usare per le squadre giovanili, i cui campionati '
                        .'non hanno tabellini pubblicati dalla Lega. Per le squadre sincronizzate i '
                        .'totali si ricostruiscono da soli e questa riga verrebbe sovrascritta.'
                    )
                    ->schema([
                        Select::make('player_id')
                            ->label('Giocatrice')
                            ->relationship('player', 'last_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            // La chiave univoca è atleta + stagione + squadra:
                            // senza questa regola un duplicato arriverebbe fino
                            // all'errore SQL.
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('season_id', $get('season_id'))
                                    ->where('team_id', $get('team_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Questa atleta ha già una riga per la stagione e la squadra scelte.',
                            ]),
                        Select::make('season_id')
                            ->label('Stagione')
                            ->relationship('season', 'name')
                            ->preload()
                            ->required()
                            ->default(fn () => Season::query()->where('is_current', true)->value('id')),
                        Select::make('team_id')
                            ->label('Squadra')
                            // Solo le squadre della società: le avversarie
                            // importate non hanno atlete in anagrafica.
                            ->relationship('team', 'name', fn (Builder $query) => $query->where('is_internal', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('I totali sono per squadra: un\'atleta schierata in A1 e in B1 ha due righe distinte.'),
                    ])->columns(3),

                Section::make('Impiego')
                    ->schema([
                        TextInput::make('matches_played')
                            ->label(self::LABEL_MATCHES_PLAYED)
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('sets_played')
                            ->label(self::LABEL_SETS_PLAYED)
                            ->numeric()->minValue(0)->default(0)->required(),
                    ])->columns(2),

                Section::make('Punti')
                    ->schema([
                        TextInput::make('points')
                            ->label('Punti totali')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('points_break')
                            ->label('Break point')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('blocks')
                            ->label('Muri punto')
                            ->numeric()->minValue(0)->default(0)->required(),
                    ])->columns(3),

                Section::make('Attacco')
                    ->schema([
                        TextInput::make('attacks')
                            // Etichetta esplicita: il campo si chiama `attacks`
                            // ma non sono i punti.
                            ->label('Attacchi tentati')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('attack_points')
                            ->label('Punti in attacco')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('attack_errors')
                            ->label('Errori')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('attack_blocked')
                            ->label('Attacchi murati')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('attack_pct')
                            ->label('Efficacia %')
                            ->numeric()->minValue(0)->maxValue(100)
                            ->suffix('%')
                            ->helperText('Punti in attacco sul totale dei tentativi.'),
                    ])->columns(3),

                Section::make('Battuta')
                    ->schema([
                        TextInput::make('aces')
                            ->label('Ace')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('serve_total')
                            ->label('Battute totali')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('serve_errors')
                            ->label('Errori')
                            ->numeric()->minValue(0)->default(0)->required(),
                    ])->columns(3),

                Section::make('Ricezione')
                    ->schema([
                        TextInput::make('receptions')
                            ->label('Ricezioni totali')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('reception_errors')
                            ->label('Errori')
                            ->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('reception_positive_pct')
                            ->label('Positive %')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                        TextInput::make('reception_perfect_pct')
                            ->label('Perfette %')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    ])->columns(4),
            ]);
    }

    /**
     * Colonne di identità: cambiano a seconda di dove si mostra la tabella.
     * Nella vista di squadra la colonna "Squadra" sarebbe una costante.
     *
     * @return array<Tables\Columns\Column>
     */
    public static function identityColumns(bool $withTeam = true): array
    {
        return array_values(array_filter([
            TextColumn::make('player.full_name')
                ->label('Atleta')
                ->searchable(['first_name', 'last_name'])
                ->sortable(['last_name', 'first_name'])
                ->weight(FontWeight::Medium),

            $withTeam
                ? TextColumn::make('team.name')
                    ->label('Squadra')
                    ->placeholder('—')
                    ->sortable()
                : null,

            TextColumn::make('season.name')
                ->label('Stagione')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ]));
    }

    /**
     * Impiego e resa complessiva: le colonne che si guardano per prime.
     *
     * @return array<Tables\Columns\Column>
     */
    public static function workloadColumns(): array
    {
        return [
            TextColumn::make('matches_played')
                ->label('PG')
                ->tooltip(self::LABEL_MATCHES_PLAYED)
                ->alignCenter()
                ->sortable(),

            TextColumn::make('sets_played')
                ->label('Set')
                ->tooltip(self::LABEL_SETS_PLAYED)
                ->alignCenter()
                ->sortable(),

            TextColumn::make('points')
                ->label('Punti')
                ->alignCenter()
                ->weight(FontWeight::Bold)
                ->sortable(),

            // Il confronto sensato tra atlete con minutaggi diversi. Non è una
            // colonna del database: l'ordinamento va scritto a mano.
            TextColumn::make('points_per_set')
                ->label('Pt/set')
                ->tooltip('Punti per set giocato')
                ->alignCenter()
                ->placeholder('—')
                ->getStateUsing(fn (PlayerStat $record): ?float => $record->pointsPerSet())
                ->numeric(decimalPlaces: 2)
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    'CASE WHEN sets_played > 0 THEN points / sets_played END '
                    .($direction === 'desc' ? 'desc' : 'asc')
                )),
        ];
    }

    /**
     * Le colonne di dettaglio, raggruppate per fondamentale.
     *
     * Sono quasi tutte nascoste per default: aperte tutte insieme la tabella
     * arriva a venti colonne numeriche e diventa illeggibile. Restano visibili
     * solo le due percentuali che riassumono il fondamentale.
     *
     * @return array<ColumnGroup>
     */
    public static function fundamentalColumnGroups(): array
    {
        return [
            ColumnGroup::make('Attacco', [
                TextColumn::make('attack_pct')
                    ->label('%')
                    ->tooltip('Punti in attacco sul totale dei tentativi')
                    ->alignCenter()
                    ->suffix('%')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('attack_points')
                    ->label('Punti')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // `attacks` = tentativi, NON punti: l'etichetta lo dice.
                TextColumn::make('attacks')
                    ->label('Tentati')
                    ->tooltip('Attacchi tentati, punti compresi')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attack_errors')
                    ->label('Errori')
                    ->alignCenter()
                    ->color('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attack_blocked')
                    ->label('Murati')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),

            ColumnGroup::make('Battuta', [
                TextColumn::make('aces')
                    ->label('Ace')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('serve_total')
                    ->label('Totali')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('serve_errors')
                    ->label('Errori')
                    ->alignCenter()
                    ->color('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),

            ColumnGroup::make('Ricezione', [
                TextColumn::make('reception_positive_pct')
                    ->label('Pos. %')
                    ->tooltip('Ricezioni positive: valore stimato, la Lega pubblica solo la percentuale per gara')
                    ->alignCenter()
                    ->suffix('%')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reception_perfect_pct')
                    ->label('Perf. %')
                    ->tooltip('Ricezioni perfette: valore stimato')
                    ->alignCenter()
                    ->suffix('%')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('receptions')
                    ->label('Totali')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reception_errors')
                    ->label('Errori')
                    ->alignCenter()
                    ->color('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),

            ColumnGroup::make('Muro e break', [
                TextColumn::make('blocks')
                    ->label('Muri')
                    ->tooltip('Punti realizzati a muro')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('points_break')
                    ->label('Break')
                    ->tooltip('Punti conquistati in break point')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),
        ];
    }

    /**
     * Origine della riga, come nella classifica: la data di sincronizzazione
     * dice anche perché la riga non si può modificare.
     */
    public static function syncColumn(): TextColumn
    {
        return TextColumn::make('last_synced_at')
            ->label('Aggiornata il')
            ->dateTime('d/m/Y H:i')
            ->placeholder('inserita a mano')
            ->description(fn (PlayerStat $record): ?string => $record->last_synced_at?->diffForHumans())
            ->sortable();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...static::identityColumns(),
                ...static::workloadColumns(),
                ...static::fundamentalColumnGroups(),
                static::syncColumn(),
            ])
            // Chi guarda queste tabelle cerca le migliori realizzatrici.
            ->defaultSort('points', 'desc')
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name')
                    ->default(Season::query()->where('is_current', true)->value('id')),
                SelectFilter::make('team_id')
                    ->label('Squadra')
                    ->relationship('team', 'name', fn (Builder $query) => $query->where('is_internal', true))
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('origin')
                    ->label('Origine')
                    ->placeholder('Tutte')
                    ->trueLabel('Importate dalla Lega')
                    ->falseLabel('Inserite a mano')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('last_synced_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('last_synced_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
                // Compare solo sulle righe inserite a mano (vedi canEdit()).
                EditAction::make(),
                DeleteAction::make(),
            ])
            // Nessuna cancellazione in blocco: la selezione multipla non
            // distingue le righe manuali da quelle rigenerate dall'import.
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->emptyStateHeading('Nessuna statistica per questi filtri')
            ->emptyStateDescription(
                'Per le squadre iscritte ai campionati della Lega i totali si ricostruiscono dai '
                .'tabellini a ogni sincronizzazione. Per le giovanili, che non hanno tabellini '
                .'pubblicati, si inseriscono a mano.'
            );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Atleta')
                    ->schema([
                        TextEntry::make('player.full_name')->label('Giocatrice'),
                        TextEntry::make('team.name')->label('Squadra')->placeholder('—'),
                        TextEntry::make('season.name')->label('Stagione'),
                    ])->columns(3),

                Section::make('Impiego e punti')
                    ->schema([
                        TextEntry::make('matches_played')->label(self::LABEL_MATCHES_PLAYED),
                        TextEntry::make('sets_played')->label(self::LABEL_SETS_PLAYED),
                        TextEntry::make('points')->label('Punti totali'),
                        TextEntry::make('points_per_set')
                            ->label('Punti per set')
                            ->placeholder('—')
                            ->state(fn (PlayerStat $record): ?float => $record->pointsPerSet()),
                        TextEntry::make('points_break')->label('Break point'),
                    ])->columns(5),

                Section::make('Attacco')
                    ->schema([
                        TextEntry::make('attack_points')->label('Punti'),
                        // Ripetuto anche qui: `attacks` non sono i punti.
                        TextEntry::make('attacks')->label('Tentati'),
                        TextEntry::make('attack_errors')->label('Errori'),
                        TextEntry::make('attack_blocked')->label('Murati'),
                        TextEntry::make('attack_pct')
                            ->label('Efficacia')
                            ->suffix('%')
                            ->placeholder('—'),
                    ])->columns(5),

                Section::make('Battuta')
                    ->schema([
                        TextEntry::make('aces')->label('Ace'),
                        TextEntry::make('serve_total')->label('Battute totali'),
                        TextEntry::make('serve_errors')->label('Errori'),
                    ])->columns(3),

                Section::make('Ricezione')
                    ->description(
                        'Le percentuali di stagione sono stimate: la Lega pubblica solo quella della '
                        .'singola gara, mai il conteggio assoluto delle ricezioni positive. Si pesa '
                        .'ogni gara sul volume di ricezione, quindi il valore è esatto a meno degli '
                        .'arrotondamenti del referto.'
                    )
                    ->schema([
                        TextEntry::make('receptions')->label('Ricezioni totali'),
                        TextEntry::make('reception_errors')->label('Errori'),
                        TextEntry::make('reception_positive_pct')
                            ->label('Positive')->suffix('%')->placeholder('—'),
                        TextEntry::make('reception_perfect_pct')
                            ->label('Perfette')->suffix('%')->placeholder('—'),
                    ])->columns(4),

                Section::make('Muro')
                    ->schema([
                        TextEntry::make('blocks')->label('Punti a muro'),
                    ]),

                Section::make('Origine dei dati')
                    ->schema([
                        TextEntry::make('last_synced_at')
                            ->label('Ultima sincronizzazione')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('mai: riga inserita a mano'),
                        TextEntry::make('origin_note')
                            ->label('Modificabile')
                            ->state(fn (PlayerStat $record): string => static::isManual($record)
                                ? 'Sì: riga inserita dal CMS, la sincronizzazione non la tocca.'
                                : 'No: riga ricostruita dai tabellini della Lega a ogni sincronizzazione, '
                                  .'una modifica manuale andrebbe persa al giro successivo.'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlayerStats::route('/'),
            'create' => CreatePlayerStat::route('/create'),
            'view' => ViewPlayerStat::route('/{record}'),
            'edit' => EditPlayerStat::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['player', 'season', 'team']);
    }

    // ─── Scrittura consentita solo sulle righe manuali ──────────
    // La policy autorizza il ruolo; qui si aggiunge il vincolo sull'origine
    // del dato, che una policy sul solo ruolo non può esprimere.

    public static function canEdit(Model $record): bool
    {
        return static::isManual($record) && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::isManual($record) && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
