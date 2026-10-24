<?php

namespace App\Filament\Resources\AuctionResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BidsRelationManager extends RelationManager
{
    protected static string $relationship = 'bids';

    protected static ?string $recordTitleAttribute = 'amount';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),
                IconColumn::make('is_valid')
                    ->label('Valida')
                    ->boolean(),
                TextColumn::make('placed_at')
                    ->label('Data Offerta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('Invalida')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->invalidate())
                    ->visible(fn ($record): bool => (bool) $record->is_valid),
            ])
            ->toolbarActions([]);
    }
}
