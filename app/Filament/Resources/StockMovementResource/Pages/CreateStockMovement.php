<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\StockMovementResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    /**
     * Uno scarico oltre la giacenza lo rifiuta lo StockMovementObserver: si
     * dice perche' invece della pagina d'errore, e il movimento non resta.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(fn (): Model => parent::handleRecordCreation($data));
        } catch (InsufficientStockException $e) {
            Notification::make()
                ->title('Giacenza insufficiente')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
