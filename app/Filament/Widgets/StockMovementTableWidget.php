<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockMovementResource;
use App\Models\StockMovement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StockMovementTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Ultimi Movimenti di Magazzino';

    protected int|string|array $columnSpan = 'full';

    // Non mostrare automaticamente: visibile solo nella MagazzinoPage
    protected static bool $isDiscovered = false;

    /**
     * Riusa colonne e filtri dal resource per evitare duplicazione.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->with(['product', 'variant', 'order'])
                    ->latest('created_at')
            )
            ->columns(StockMovementResource::getMovementColumns())
            ->filters(StockMovementResource::getMovementFilters())
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (StockMovement $record): string => StockMovementResource::getUrl('index')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('nuovo_movimento')
                    ->label('Nuovo Movimento')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(StockMovementResource::getUrl('create')),
                Tables\Actions\Action::make('vedi_tutti')
                    ->label('Vedi storico completo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(StockMovementResource::getUrl('index')),
            ])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Nessun movimento registrato')
            ->emptyStateDescription('I movimenti appariranno qui quando verranno effettuati ordini o carichi manuali.');
    }
}
