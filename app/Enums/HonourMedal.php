<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Piazzamento di una medaglia in nazionale.
 *
 * Sui trofei di club il campo resta null: un palmarès elenca le coppe alzate,
 * non i secondi posti, e forzare `gold` su ogni riga renderebbe impossibile
 * distinguere "titolo vinto" da "oro conquistato" nel banner.
 */
enum HonourMedal: string implements HasColor, HasLabel
{
    case Gold = 'gold';
    case Silver = 'silver';
    case Bronze = 'bronze';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gold => 'Oro',
            self::Silver => 'Argento',
            self::Bronze => 'Bronzo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Gold => 'warning',
            self::Silver => 'gray',
            self::Bronze => 'danger',
        };
    }
}
