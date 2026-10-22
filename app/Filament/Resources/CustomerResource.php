<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clienti';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clienti';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'shop/clienti';

    protected static bool $isGloballySearchable = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('orders_count')
                    ->label('N° Ordini')
                    ->counts('orders')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('orders_sum_total_price')
                    ->label('Totale Speso')
                    ->sum('orders', 'total_price')
                    ->money('EUR')
                    ->sortable()
                    ->weight(FontWeight::Bold),
                TextColumn::make('latest_order_date')
                    ->label('Ultimo Ordine')
                    ->getStateUsing(fn (User $record) => $record->orders()->latest()->value('created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->orderBy(
                            Order::select('created_at')
                                ->whereColumn('orders.user_id', 'users.id')
                                ->latest()
                                ->limit(1),
                            $direction
                        );
                    })
                    ->placeholder('Nessun ordine'),
                TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('has_orders')
                    ->label('Con ordini')
                    ->query(fn (Builder $query) => $query->has('orders'))
                    ->toggle(),
                Filter::make('registered_recently')
                    ->label('Ultimi 30 giorni')
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati Cliente')
                    ->schema([
                        TextEntry::make('name')->label('Nome'),
                        TextEntry::make('email')->label('Email'),
                        TextEntry::make('phone')->label('Telefono')->placeholder('-'),
                        TextEntry::make('created_at')->label('Registrato il')->dateTime('d/m/Y H:i'),
                    ])->columns(2),
                Section::make('Riepilogo Acquisti')
                    ->schema([
                        TextEntry::make('orders_count')
                            ->label('Ordini Totali')
                            ->getStateUsing(fn (User $record) => $record->orders()->count()),
                        TextEntry::make('total_spent')
                            ->label('Totale Speso')
                            ->getStateUsing(fn (User $record) => '€ '.number_format($record->orders()->sum('total_price'), 2, ',', '.')),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('role', [UserRole::User->value, UserRole::Customer->value]);
    }
}
