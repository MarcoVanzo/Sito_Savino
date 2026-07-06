<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clienti';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Clienti';
    protected static ?string $navigationGroup = 'Shop Ufficiale';
    protected static ?int $navigationSort = 3;
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefono')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('N° Ordini')
                    ->counts('orders')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('orders_sum_total_price')
                    ->label('Totale Speso')
                    ->sum('orders', 'total_price')
                    ->money('EUR')
                    ->sortable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('latest_order_date')
                    ->label('Ultimo Ordine')
                    ->getStateUsing(fn (User $record) => $record->orders()->latest()->value('created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->orderBy(
                            \App\Models\Order::select('created_at')
                                ->whereColumn('orders.user_id', 'users.id')
                                ->latest()
                                ->limit(1),
                            $direction
                        );
                    })
                    ->placeholder('Nessun ordine'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('has_orders')
                    ->label('Con ordini')
                    ->query(fn (Builder $query) => $query->has('orders'))
                    ->toggle(),
                Tables\Filters\Filter::make('registered_recently')
                    ->label('Ultimi 30 giorni')
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Dati Cliente')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->label('Nome'),
                        Infolists\Components\TextEntry::make('email')->label('Email'),
                        Infolists\Components\TextEntry::make('phone')->label('Telefono')->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')->label('Registrato il')->dateTime('d/m/Y H:i'),
                    ])->columns(2),
                Infolists\Components\Section::make('Riepilogo Acquisti')
                    ->schema([
                        Infolists\Components\TextEntry::make('orders_count')
                            ->label('Ordini Totali')
                            ->getStateUsing(fn (User $record) => $record->orders()->count()),
                        Infolists\Components\TextEntry::make('total_spent')
                            ->label('Totale Speso')
                            ->getStateUsing(fn (User $record) => '€ ' . number_format($record->orders()->sum('total_price'), 2, ',', '.')),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('role', [UserRole::User->value, UserRole::Customer->value]);
    }
}
