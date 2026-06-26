<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'publish';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Published => 'Pubblicato',
        };
    }
}
