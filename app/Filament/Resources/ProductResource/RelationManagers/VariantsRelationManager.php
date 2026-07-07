<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                ->label('Taglia')
                ->placeholder('es. S, M, L, XL'),
            Forms\Components\TextInput::make('color')
                ->label('Colore')
                ->placeholder('es. Bianco, Nero'),
            Forms\Components\TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('price_modifier')
                ->label('Variazione Prezzo (€)')
                ->numeric()
                ->default(0)
                ->prefix('€')
                ->helperText('Aggiunto al prezzo base del prodotto. Es: +5.00 per taglia XL.'),
            Forms\Components\TextInput::make('stock')
                ->label('Stock')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('Stock attuale per questa variante. Puoi modificarlo manualmente o tramite i Movimenti Magazzino.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('size')
                    ->label('Taglia')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('color')
                    ->label('Colore')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('price_modifier')
                    ->label('Mod. Prezzo')
                    ->money('EUR')
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : null)),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (int $state): string => $state > 5 ? 'success' : ($state > 0 ? 'warning' : 'danger')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuova Variante'),
            ])
            ->actions([
                // Azione rapida per aggiornare solo lo stock senza aprire il form completo
                Tables\Actions\Action::make('update_stock')
                    ->label('Stock')
                    ->icon('heroicon-o-cube')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('stock')
                            ->label('Nuovo Stock')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(fn ($record) => $record->stock),
                    ])
                    ->action(function ($record, array $data): void {
                        $diff = (int) $data['stock'] - $record->stock;
                        if ($diff === 0) {
                            Notification::make()->title('Stock invariato')->info()->send();
                            return;
                        }

                        \App\Models\StockMovement::create([
                            'product_id' => $record->product_id,
                            'product_variant_id' => $record->id,
                            'quantity' => $diff,
                            'type' => \App\Enums\StockMovementType::Adjustment,
                            'notes' => "Aggiustamento manuale CMS: {$record->stock} → {$data['stock']}",
                        ]);

                        Notification::make()
                            ->title('Stock aggiornato')
                            ->body("Variante {$record->sku}: {$record->stock} → {$data['stock']} (movimento registrato)")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
