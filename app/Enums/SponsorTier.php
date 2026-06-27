<?php

namespace App\Enums;

enum SponsorTier: string
{
    case Main = 'main';
    case Gold = 'gold';
    case Silver = 'silver';
    case Technical = 'technical';
    case Standard = 'standard';

    public function label(): string
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
