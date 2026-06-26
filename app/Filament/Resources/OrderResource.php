<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Ordine';
    protected static ?string $pluralModelLabel = 'Ordini';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Shop';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Ordine')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Cliente (Utente)')
                            ->relationship('user', 'name')
                            ->searchable(),
                        Forms\Components\Select::make('status')
                            ->label('Stato Ordine')
                            ->options([
                                'pending' => 'In Attesa',
                                'paid' => 'Pagato',
                                'shipped' => 'Spedito',
                                'cancelled' => 'Annullato',
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\TextInput::make('total_price')
                            ->label('Totale Ordine (€)')
                            ->required()
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\TextInput::make('stripe_payment_id')
                            ->label('ID Pagamento Stripe')
                            ->maxLength(255),
                    ])->columns(2),
                Forms\Components\Section::make('Indirizzi')
                    ->schema([
                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Indirizzo di Spedizione')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('billing_address')
                            ->label('Indirizzo di Fatturazione')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato Ordine')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'shipped' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Totale')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data Ordine')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato Ordine')
                    ->options([
                        'pending' => 'In Attesa',
                        'paid' => 'Pagato',
                        'shipped' => 'Spedito',
                        'cancelled' => 'Annullato',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
