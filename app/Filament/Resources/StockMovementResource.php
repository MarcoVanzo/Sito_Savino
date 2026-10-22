<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovementResource\Pages\CreateStockMovement;
use App\Filament\Resources\StockMovementResource\Pages\ListStockMovements;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $isGloballySearchable = false;

    // Nascosto dalla nav: il Magazzino è gestito dalla custom page MagazzinoPage
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?string $navigationLabel = 'Magazzino';

    protected static ?string $modelLabel = 'Movimento Magazzino';

    protected static ?string $pluralModelLabel = 'Movimenti Magazzino';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Movimento')
                    ->schema([
                        Select::make('product_id')
                            ->label('Prodotto')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('product_variant_id')
                            ->label('Variante')
                            // Un prodotto con taglie si carica e si scarica sulla
                            // taglia: un movimento sul solo prodotto spostava il
                            // riepilogo, che le taglie poi riallineano.
                            ->required(fn (Get $get): bool => filled($get('product_id'))
                                && ProductVariant::where('product_id', $get('product_id'))->exists())
                            ->relationship('variant', 'sku', fn (Builder $query, Get $get) => $query->where('product_id', $get('product_id'))
                            )
                            ->searchable()
                            ->preload(),
                        Select::make('type')
                            ->label('Tipo di Movimento')
                            ->options([
                                StockMovementType::Purchase->value => StockMovementType::Purchase->getLabel(),
                                StockMovementType::Adjustment->value => StockMovementType::Adjustment->getLabel(),
                            ])
                            ->required()
                            ->default(StockMovementType::Purchase)
                            ->helperText('Le vendite vengono registrate automaticamente dagli ordini.'),
                        TextInput::make('quantity')
                            ->label('Quantità')
                            ->numeric()
                            ->required()
                            ->helperText('Usa valori positivi per aggiungere (es. 50), negativi per togliere (es. -5).'),
                    ])->columns(2),
                Section::make('Informazioni Aggiuntive')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Colonne della tabella movimenti, estratte per riuso nel widget Magazzino.
     */
    public static function getMovementColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Data')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('product.name')
                ->label('Prodotto')
                ->searchable()
                ->sortable(),
            TextColumn::make('variant.sku')
                ->label('SKU Variante')
                ->searchable()
                ->sortable(),
            TextColumn::make('type')
                ->label('Tipo')
                ->badge()
                ->formatStateUsing(fn (StockMovementType $state): string => $state->getLabel())
                ->color(fn (StockMovementType $state): string => match ($state) {
                    StockMovementType::Purchase => 'success',
                    StockMovementType::Sale => 'danger',
                    StockMovementType::Adjustment => 'warning',
                }),
            TextColumn::make('quantity')
                ->label('Q.tà')
                ->numeric()
                ->sortable(),
            TextColumn::make('order.id')
                ->label('Ordine #')
                ->placeholder('Manuale')
                ->sortable(),
            TextColumn::make('notes')
                ->label('Note')
                ->limit(30)
                ->searchable(),
        ];
    }

    /**
     * Filtri della tabella movimenti, estratti per riuso nel widget Magazzino.
     */
    public static function getMovementFilters(): array
    {
        return [
            SelectFilter::make('type')
                ->label('Tipo Movimento')
                ->options(StockMovementType::class),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::getMovementColumns())
            ->defaultSort('created_at', 'desc')
            ->filters(static::getMovementFilters())
            ->recordActions([
                ViewAction::make(),
                // Nessun EditAction: i movimenti di magazzino sono record di audit immutabili
            ])
            ->toolbarActions([
                // Nessun bulk delete: i movimenti non devono essere cancellati
            ]);
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
            'index' => ListStockMovements::route('/'),
            'create' => CreateStockMovement::route('/create'),
            // Nessuna pagina edit: i movimenti sono immutabili
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'variant', 'order']);
    }
}
