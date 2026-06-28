<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Order::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Ordine';

    protected static ?string $pluralModelLabel = 'Ordini';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 14;

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
                            ->options(OrderStatus::class)
                            ->required()
                            ->default(OrderStatus::Pending),
                        Forms\Components\TextInput::make('total_price')
                            ->label('Totale Ordine (€)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        Forms\Components\TextInput::make('stripe_payment_id')
                            ->label('ID Pagamento Stripe')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Gestito automaticamente dal sistema di pagamento.'),
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
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'warning',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Shipped => 'info',
                        OrderStatus::Cancelled => 'danger',
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
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato Ordine')
                    ->options(OrderStatus::class),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions(static::viewAndEditActions())
            ->bulkActions(static::softDeleteBulkActions());
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user']);
    }
}
