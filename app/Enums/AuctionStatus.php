<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AuctionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Scheduled => 'Programmata',
            self::Active => 'Attiva',
            self::Ended => 'Conclusa',
            self::Cancelled => 'Annullata',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Active => 'success',
            self::Ended => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
