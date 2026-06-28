<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PostStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'publish';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Published => 'Pubblicato',
        };
    }
}
