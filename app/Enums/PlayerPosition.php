<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlayerPosition: string implements HasLabel
{
    case Setter = 'palleggiatrice';
    case OutsideHitter = 'schiacciatrice';
    case Opposite = 'opposto';
    case MiddleBlocker = 'centrale';
    case Libero = 'libero';

    public function getLabel(): string
    {
        return match ($this) {
            self::Setter => 'Palleggiatrice',
            self::OutsideHitter => 'Schiacciatrice',
            self::Opposite => 'Opposto',
            self::MiddleBlocker => 'Centrale',
            self::Libero => 'Libero',
        };
    }
}
