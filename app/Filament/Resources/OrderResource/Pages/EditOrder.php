<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Nessuna azione di cancellazione: gli ordini si conservano per obbligo
     * fiscale e OrderPolicy::delete() la nega a tutti, super admin compreso.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
