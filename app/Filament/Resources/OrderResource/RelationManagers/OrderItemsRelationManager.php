<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->relationship('variant', 'sku', fn (\Illuminate\Database\Eloquent\Builder $query, Forms\Get $get) => 
                        $query->where('product_id', $get('product_id'))
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

    public function table(Table $table): Table
    {
        return $table
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
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
