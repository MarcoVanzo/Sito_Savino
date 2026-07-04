<?php

namespace App\Filament\Resources\AuctionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BidsRelationManager extends RelationManager
{
    protected static string $relationship = 'bids';

    protected static ?string $recordTitleAttribute = 'amount';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_valid')
                    ->label('Valida')
                    ->boolean(),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Data Offerta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('Invalida')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->invalidate())
                    ->visible(fn ($record): bool => (bool) $record->is_valid),
            ])
            ->bulkActions([]);
    }
}
