<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\Resources\TeamResource\Pages\EditTeam;
use App\Filament\Resources\TeamResource\Pages\ListTeams;
use App\Filament\Resources\TeamResource\RelationManagers\RostersRelationManager;
use App\Filament\Resources\TeamResource\RelationManagers\SeasonStatsRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Team;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class TeamResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Team::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Squadra';

    protected static ?string $pluralModelLabel = 'Squadre';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    // La tabella `teams` ospita anche le avversarie importate dalla Lega, ed è
    // qui che se ne carica il logo: la voce sta quindi accanto a classifica e
    // risultati e non più solo nella sezione youth.
    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?string $navigationLabel = 'Squadre';

    protected static ?int $navigationSort = 5;

    /**
     * Categorie proposte nel form: alle tre youth si aggiungono quelle già
     * presenti in archivio, così una categoria fuori elenco (o l'assenza di
     * categoria delle squadre importate) non viene azzerata al salvataggio.
     *
     * @return array<string, string>
     */
    private static function categoryOptions(): array
    {
        return collect([
            'A1' => 'Serie A1',
            'B1' => 'Serie B1',
            'U17' => 'Serie U17',
            'U15' => 'Serie U15',
        ])->union(
            Team::query()
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category', 'category')
                ->all()
        )->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Squadra')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Squadra')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('category')
                            ->label('Categoria')
                            ->options(fn () => self::categoryOptions())
                            ->helperText('Determina a quale sotto-sezione youth appartiene. Le avversarie importate dalla Lega non hanno categoria.'),
                    ])->columns(2),

                Section::make('Logo')
                    ->description('Il logo caricato qui è quello che il sito usa e non viene mai sovrascritto dalla sincronizzazione con la Lega, che scrive su una collezione separata.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('custom_logo')
                            ->label('Logo caricato dal CMS')
                            // Collezione `logo` (Team::LOGO_CUSTOM): la
                            // sincronizzazione scrive solo su `logo-lvf`.
                            ->collection(Team::LOGO_CUSTOM)
                            ->image()
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->helperText('Ha la precedenza sul logo scaricato dalla Lega. Rimuovendolo si torna automaticamente a quello importato.'),

                        // Sola lettura: il logo della Lega si mostra come
                        // anteprima e non come campo di upload, così non c'è
                        // modo di sovrascriverlo o cancellarlo per sbaglio.
                        Placeholder::make('imported_logo')
                            ->label('Logo importato dalla Lega (sola lettura)')
                            ->content(function (?Team $record): HtmlString|string {
                                $media = $record?->getFirstMedia(Team::LOGO_IMPORTED);

                                if ($media === null) {
                                    return 'Nessun logo importato dalla Lega per questa squadra.';
                                }

                                return new HtmlString(
                                    '<img src="'.e($media->getUrl()).'" alt="" style="max-height:96px;width:auto">'
                                );
                            }),

                        Placeholder::make('logo_in_use')
                            ->label('Logo attualmente in uso')
                            ->content(function (?Team $record): string {
                                if ($record?->hasCustomLogo()) {
                                    return 'Quello caricato dal CMS.';
                                }

                                if ($record?->getFirstMedia(Team::LOGO_IMPORTED) !== null) {
                                    return 'Quello importato dalla Lega: caricandone uno qui sopra verrà usato il tuo.';
                                }

                                return filled($record?->logo_url)
                                    ? 'Nessun file caricato: si usa l\'indirizzo remoto del logo della Lega.'
                                    : 'Nessun logo disponibile.';
                            })
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Dati dalla Lega')
                    ->description('Valori gestiti dalla sincronizzazione: si mostrano solo per chiarezza.')
                    ->schema([
                        TextInput::make('lvf_club_id')
                            ->label('ID club Lega')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('non agganciata')
                            ->helperText('Identificativo della società sul sito della Lega. Cambia a ogni stagione ed è gestito dall\'import.'),
                        Placeholder::make('is_internal_label')
                            ->label('Tipo squadra')
                            ->content(fn (?Team $record): string => $record?->is_internal
                                ? 'Squadra della società'
                                : 'Squadra avversaria (creata dall\'import della Lega)'),
                    ])->columns(2)
                    ->collapsed()
                    ->collapsible()
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Logo effettivo: custom se c'è, altrimenti quello della Lega
                // o l'indirizzo remoto (Team::logoUrl()).
                ImageColumn::make('logo')
                    ->checkFileExistence(false)
                    ->label('')
                    ->height(32)
                    ->getStateUsing(fn (Team $record) => $record->logoUrl()),
                TextColumn::make('name')
                    ->label('Nome Squadra')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('is_internal')
                    ->label('Nostra')
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('has_custom_logo')
                    ->label('Logo CMS')
                    ->alignCenter()
                    ->boolean()
                    ->getStateUsing(fn (Team $record) => $record->hasCustomLogo())
                    ->tooltip(fn (Team $record) => $record->hasCustomLogo()
                        ? 'Logo caricato dal CMS: la sincronizzazione non lo tocca'
                        : 'Nessun logo caricato dal CMS: si usa quello della Lega'),
                TextColumn::make('lvf_club_id')
                    ->label('ID Lega')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(fn () => self::categoryOptions()),
                TernaryFilter::make('is_internal')
                    ->label('Tipo squadra')
                    ->placeholder('Tutte')
                    ->trueLabel('Squadre della società')
                    ->falseLabel('Avversarie importate'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            RostersRelationManager::class,
            // Solo per le squadre della società: si nasconde da sola sulle
            // avversarie importate (canViewForRecord).
            SeasonStatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Prima l'elenco era limitato alle categorie youth: le avversarie
        // importate dalla Lega non erano raggiungibili e non se ne poteva
        // caricare il logo.
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with('media');
    }
}
