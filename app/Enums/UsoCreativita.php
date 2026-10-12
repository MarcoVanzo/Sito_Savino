<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Dove va in onda una creatività sponsor della diretta.
 *
 * - `Preroll`: il video (o l'immagine) che il visitatore vede prima della
 *   diretta, in un player del sito, mai sopra quello di YouTube;
 * - `Cornice`: la cornice a L che compare accanto al player durante la gara.
 */
enum UsoCreativita: string implements HasLabel
{
    case Preroll = 'preroll';
    case Cornice = 'cornice';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preroll => 'Prima della diretta (pre-roll)',
            self::Cornice => 'Cornice durante la gara',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preroll => 'info',
            self::Cornice => 'warning',
        };
    }
}
