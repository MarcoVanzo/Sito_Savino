<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Sale = 'Vendita';
    case Purchase = 'Acquisto';
    case Adjustment = 'Rettifica';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Vendita',
            self::Purchase => 'Acquisto',
            self::Adjustment => 'Rettifica',
        };
    }
}
