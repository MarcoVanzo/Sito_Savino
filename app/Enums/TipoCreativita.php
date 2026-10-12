<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoCreativita: string implements HasLabel
{
    case Video = 'video';
    case Immagine = 'immagine';

    public function getLabel(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Immagine => 'Immagine',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Video => 'heroicon-o-film',
            self::Immagine => 'heroicon-o-photo',
        };
    }
}
