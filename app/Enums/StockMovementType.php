<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Sale = 'Vendita';
    case Purchase = 'Acquisto';
    case Adjustment = 'Rettifica';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sale => 'Vendita',
            self::Purchase => 'Acquisto',
            self::Adjustment => 'Rettifica',
        };
    }
}
