<?php

namespace App\Enums;

enum PlayerPosition: string
{
    case Setter = 'palleggiatrice';
    case OutsideHitter = 'schiacciatrice';
    case Opposite = 'opposto';
    case MiddleBlocker = 'centrale';
    case Libero = 'libero';

    public function label(): string
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
