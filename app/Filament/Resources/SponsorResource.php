<?php

namespace App\Filament\Resources;

use App\Enums\SponsorTier;
use App\Filament\Resources\SponsorResource\Pages\CreateSponsor;
use App\Filament\Resources\SponsorResource\Pages\EditSponsor;
use App\Filament\Resources\SponsorResource\Pages\ListSponsors;
use App\Filament\Resources\SponsorResource\RelationManagers\LiveCreativesRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Sponsor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/*
 * Gli sponsor non hanno testi da tradurre: il nome e' un marchio e
 * l'indirizzo e' lo stesso in ogni lingua. Il model dichiara infatti
 * `$translatable = []`, e il plugin delle traduzioni rifiuta un elenco vuoto:
 * la scheda di uno sponsor rispondeva 500 e non si poteva aprire.
 */
class SponsorResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Sponsor::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Sponsor';

    protected static ?string $pluralModelLabel = 'Sponsor';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'I Nostri Sponsor';

    protected static string|\UnitEnum|null $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'sponsor';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->url()
                    ->maxLength(255),
                Select::make('tier')
                    ->label('Livello')
                    ->options(SponsorTier::class)
                    ->default(SponsorTier::Official)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->helperText('Posizione dentro al proprio livello: numeri più bassi vengono prima.')
                    ->numeric()
                    ->default(0),
                Toggle::make('solo_diretta')
                    ->label('Solo diretta')
                    ->helperText('Non compare nella pagina Sponsor né nella striscia della home: serve solo per le grafiche della diretta (es. la cornice dello shop ufficiale).')
                    ->default(false)
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('logo')
                    ->collection('sponsors')
                    ->image()
                    ->maxSize(2048)
                    ->columnSpanFull(),
                TextInput::make('testo_alternativo')
                    ->label('Testo alternativo del logo')
                    ->helperText('Se resta vuoto si usa il nome dello sponsor, che di solito basta.')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Logo')
                    ->collection('sponsors')
                    ->circular(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('tier')
                    ->label('Livello')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof SponsorTier ? $state->size() : 'small') {
                        'hero' => 'success',
                        'large' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('url')
                    ->label('Sito Web'),
                IconColumn::make('solo_diretta')
                    ->label('Solo diretta')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('tier')
                    ->label('Livello')
                    ->options(SponsorTier::class),
            ])
            ->recordActions(static::viewAndEditActions())
            ->toolbarActions(static::standardBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            LiveCreativesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsors::route('/'),
            'create' => CreateSponsor::route('/create'),
            'edit' => EditSponsor::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }
}
