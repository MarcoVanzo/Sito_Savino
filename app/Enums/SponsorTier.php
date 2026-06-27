<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SponsorTier: string implements HasLabel
{
    case Main = 'main';
    case Gold = 'gold';
    case Silver = 'silver';
    case Technical = 'technical';
    case Standard = 'standard';

    public function getLabel(): string
    {
        return match ($this) {
            self::Main => 'Main Sponsor',
            self::Gold => 'Gold Sponsor',
            self::Silver => 'Silver Sponsor',
            self::Technical => 'Technical Partner',
            self::Standard => 'Sponsor',
        };
    }
}
