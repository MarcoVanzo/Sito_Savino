<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';
    protected static ?string $title = 'Varianti';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('size')
                ->label('Taglia'),
            Forms\Components\TextInput::make('color')
                ->label('Colore'),
            Forms\Components\TextInput::make('sku')
                ->label('SKU')
                ->required(),
            Forms\Components\TextInput::make('price_modifier')
                ->label('Mod. Prezzo (€)')
                ->numeric()
                ->default(0),
            Forms\Components\TextInput::make('stock')
                ->label('Stock')
                ->numeric()
                ->default(0)
                ->disabled()
                ->helperText('Lo stock è gestito tramite Movimenti Magazzino'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('size')->label('Taglia'),
                Tables\Columns\TextColumn::make('color')->label('Colore'),
                Tables\Columns\TextColumn::make('price_modifier')
                    ->label('Mod. Prezzo')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (int $state): string => $state > 5 ? 'success' : ($state > 0 ? 'warning' : 'danger')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
