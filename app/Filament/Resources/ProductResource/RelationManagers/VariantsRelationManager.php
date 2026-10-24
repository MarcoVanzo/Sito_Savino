<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varianti';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('size')
                ->label('Taglia')
                ->placeholder('es. S, M, L, XL'),
            TextInput::make('color')
                ->label('Colore')
                ->placeholder('es. Bianco, Nero'),
            TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('price_modifier')
                ->label('Variazione Prezzo (€)')
                ->numeric()
                ->required()
                ->default(0)
                ->prefix('€')
                ->helperText('Aggiunto al prezzo base del prodotto. Es: +5.00 per taglia XL.'),
            TextInput::make('stock')
                ->label('Stock')
                ->numeric()
                ->required()
                ->default(0)
                ->minValue(0)
                ->helperText('Stock attuale per questa variante. Puoi modificarlo manualmente o tramite i Movimenti Magazzino.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::Bold),
                TextColumn::make('size')
                    ->label('Taglia')
                    ->placeholder('-'),
                TextColumn::make('color')
                    ->label('Colore')
                    ->placeholder('-'),
                TextColumn::make('price_modifier')
                    ->label('Mod. Prezzo')
                    ->money('EUR')
                    ->color(function ($state) {
                        if ($state > 0) {
                            return 'success';
                        }

                        return $state < 0 ? 'danger' : null;
                    }),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->badge()
                    ->color(function (int $state): string {
                        if ($state > 5) {
                            return 'success';
                        }

                        return $state > 0 ? 'warning' : 'danger';
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuova Variante'),
            ])
            ->recordActions([
                // Azione rapida per aggiornare solo lo stock senza aprire il form completo
                Action::make('update_stock')
                    ->label('Stock')
                    ->icon('heroicon-o-cube')
                    ->color('warning')
                    ->schema([
                        TextInput::make('stock')
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

                        StockMovement::create([
                            'product_id' => $record->product_id,
                            'product_variant_id' => $record->id,
                            'quantity' => $diff,
                            'type' => StockMovementType::Adjustment,
                            'notes' => "Aggiustamento manuale CMS: {$record->stock} → {$data['stock']}",
                        ]);

                        Notification::make()
                            ->title('Stock aggiornato')
                            ->body("Variante {$record->sku}: {$record->stock} → {$data['stock']} (movimento registrato)")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
