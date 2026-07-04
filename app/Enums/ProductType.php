<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Simple = 'simple';
    case Variable = 'variable';
    case Auction = 'auction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Simple => 'Semplice',
            self::Variable => 'Con Varianti',
            self::Auction => 'Asta',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Simple => 'success',
            self::Variable => 'info',
            self::Auction => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Simple => 'heroicon-o-cube',
            self::Variable => 'heroicon-o-squares-2x2',
            self::Auction => 'heroicon-o-fire',
        };
    }
}
