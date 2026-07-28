<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Filament\Resources\TeamResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    // La tabella `teams` ospita anche le avversarie importate dalla Lega, ed è
    // qui che se ne carica il logo: la voce sta quindi accanto a classifica e
    // risultati e non più solo nella sezione youth.
    protected static ?string $navigationGroup = 'Stagione';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Squadra')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Squadra')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('category')
                            ->label('Categoria')
                            ->options(fn () => static::categoryOptions())
                            ->helperText('Determina a quale sotto-sezione youth appartiene. Le avversarie importate dalla Lega non hanno categoria.'),
                    ])->columns(2),

                Forms\Components\Section::make('Logo')
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
                            ->imageResizeTargetWidth(1200)
                            ->imageResizeTargetHeight(1200)
                            ->imageResizeUpscale(false)
                            ->helperText('Ha la precedenza sul logo scaricato dalla Lega. Rimuovendolo si torna automaticamente a quello importato.'),

                        // Sola lettura: il logo della Lega si mostra come
                        // anteprima e non come campo di upload, così non c'è
                        // modo di sovrascriverlo o cancellarlo per sbaglio.
                        Forms\Components\Placeholder::make('imported_logo')
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

                        Forms\Components\Placeholder::make('logo_in_use')
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

                Forms\Components\Section::make('Dati dalla Lega')
                    ->description('Valori gestiti dalla sincronizzazione: si mostrano solo per chiarezza.')
                    ->schema([
                        Forms\Components\TextInput::make('lvf_club_id')
                            ->label('ID club Lega')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('non agganciata')
                            ->helperText('Identificativo della società sul sito della Lega. Cambia a ogni stagione ed è gestito dall\'import.'),
                        Forms\Components\Placeholder::make('is_internal_label')
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
                Tables\Columns\ImageColumn::make('logo')
                    ->checkFileExistence(false)
                    ->label('')
                    ->height(32)
                    ->getStateUsing(fn (Team $record) => $record->logoUrl()),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Squadra')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_internal')
                    ->label('Nostra')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('has_custom_logo')
                    ->label('Logo CMS')
                    ->alignCenter()
                    ->boolean()
                    ->getStateUsing(fn (Team $record) => $record->hasCustomLogo())
                    ->tooltip(fn (Team $record) => $record->hasCustomLogo()
                        ? 'Logo caricato dal CMS: la sincronizzazione non lo tocca'
                        : 'Nessun logo caricato dal CMS: si usa quello della Lega'),
                Tables\Columns\TextColumn::make('lvf_club_id')
                    ->label('ID Lega')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(fn () => static::categoryOptions()),
                Tables\Filters\TernaryFilter::make('is_internal')
                    ->label('Tipo squadra')
                    ->placeholder('Tutte')
                    ->trueLabel('Squadre della società')
                    ->falseLabel('Avversarie importate'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RostersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
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
