<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\OrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Ordini';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('# Ordine')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'gray',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Shipped => 'info',
                        OrderStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_price')
                    ->label('Totale')
                    ->money('EUR'),
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
