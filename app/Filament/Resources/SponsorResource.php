<?php

namespace App\Filament\Resources;

use App\Enums\SponsorTier;
use App\Filament\Resources\SponsorResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Sponsor;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'I Nostri Sponsor';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'sponsor';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('url')
                    ->url()
                    ->maxLength(255),
                Forms\Components\Select::make('tier')
                    ->label('Livello')
                    ->options(SponsorTier::class)
                    ->default(SponsorTier::Official)
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordine')
                    ->helperText('Posizione dentro al proprio livello: numeri più bassi vengono prima.')
                    ->numeric()
                    ->default(0),
                SpatieMediaLibraryFileUpload::make('logo')
                    ->collection('sponsors')
                    ->image()
                    ->maxSize(2048)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('logo')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Logo')
                    ->collection('sponsors')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tier')
                    ->label('Livello')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof SponsorTier ? $state->size() : 'small') {
                        'hero' => 'success',
                        'large' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('url')
                    ->label('Sito Web'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label('Livello')
                    ->options(SponsorTier::class),
            ])
            ->actions(static::viewAndEditActions())
            ->bulkActions(static::standardBulkActions());
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
            'index' => Pages\ListSponsors::route('/'),
            'create' => Pages\CreateSponsor::route('/create'),
            'edit' => Pages\EditSponsor::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }
}
