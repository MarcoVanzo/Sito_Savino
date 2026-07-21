<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Articoli Ordine';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Prodotto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Forms\Components\Select::make('product_variant_id')
                    ->label('Variante')
                    ->relationship('variant', 'sku', fn (Builder $query, Forms\Get $get) => $query->where('product_id', $get('product_id'))
                    )
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantità')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                Forms\Components\TextInput::make('price_at_time_of_purchase')
                    ->label('Prezzo Unitario (€)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€'),
            ]);
    }

    /**
     * Riallinea il totale dell'ordine dopo ogni modifica agli articoli:
     * altrimenti total_price resterebbe quello calcolato al checkout.
     */
    protected function recalculateOrderTotal(): void
    {
        $order = $this->getOwnerRecord();

        if ($order instanceof Order) {
            $order->recalculateTotal();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant']))
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Prodotto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('variant.sku')
                    ->label('Variante SKU')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantità')
                    ->numeric(),
                Tables\Columns\TextColumn::make('price_at_time_of_purchase')
                    ->label('Prezzo Unitario')
                    ->money('EUR'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn () => $this->recalculateOrderTotal()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn () => $this->recalculateOrderTotal()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->recalculateOrderTotal()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn () => $this->recalculateOrderTotal()),
                ]),
            ]);
    }
}
