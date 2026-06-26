<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Paid => 'Pagato',
            self::Shipped => 'Spedito',
            self::Cancelled => 'Annullato',
        };
    }
}
