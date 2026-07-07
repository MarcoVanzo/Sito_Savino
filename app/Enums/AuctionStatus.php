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
            self::Draft => __('enums.auction_status.draft'),
            self::Scheduled => __('enums.auction_status.scheduled'),
            self::Active => __('enums.auction_status.active'),
            self::Ended => __('enums.auction_status.ended'),
            self::Cancelled => __('enums.auction_status.cancelled'),
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
