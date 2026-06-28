<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Paid => 'Pagato',
            self::Shipped => 'Spedito',
            self::Cancelled => 'Annullato',
        };
    }
}
