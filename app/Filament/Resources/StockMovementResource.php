<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?string $navigationLabel = 'Magazzino';

    protected static ?string $modelLabel = 'Movimento Magazzino';

    protected static ?string $pluralModelLabel = 'Movimenti Magazzino';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Movimento')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Prodotto')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('product_variant_id')
                            ->label('Variante (Opzionale)')
                            ->relationship('variant', 'sku', fn (Builder $query, Forms\Get $get) => $query->where('product_id', $get('product_id'))
                            )
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('type')
                            ->label('Tipo di Movimento')
                            ->options([
                                StockMovementType::Purchase->value => StockMovementType::Purchase->label(),
                                StockMovementType::Adjustment->value => StockMovementType::Adjustment->label(),
                            ])
                            ->required()
                            ->default(StockMovementType::Purchase)
                            ->helperText('Le vendite vengono registrate automaticamente dagli ordini.'),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantità')
                            ->numeric()
                            ->required()
                            ->helperText('Usa valori positivi per aggiungere (es. 50), negativi per togliere (es. -5).'),
                    ])->columns(2),
                Forms\Components\Section::make('Informazioni Aggiuntive')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Prodotto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('variant.sku')
                    ->label('SKU Variante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (StockMovementType $state): string => $state->label())
                    ->color(fn (StockMovementType $state): string => match ($state) {
                        StockMovementType::Purchase => 'success',
                        StockMovementType::Sale => 'danger',
                        StockMovementType::Adjustment => 'warning',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Q.tà')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Ordine #')
                    ->placeholder('Manuale')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Note')
                    ->limit(30)
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo Movimento')
                    ->options(StockMovementType::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Nessun EditAction: i movimenti di magazzino sono record di audit immutabili
            ])
            ->bulkActions([
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
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            // Nessuna pagina edit: i movimenti sono immutabili
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['product', 'variant', 'order']);
    }
}
