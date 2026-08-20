<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Le tre famiglie in cui si legge un palmarès di pallavolo, nell'ordine in cui
 * vanno pubblicate: prima i trofei vinti col club, poi le medaglie in nazionale,
 * infine i riconoscimenti personali.
 */
enum PlayerHonourCategory: string implements HasLabel
{
    case Club = 'club';
    case National = 'national';
    case Individual = 'individual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Club => 'Club',
            self::National => 'Nazionale',
            self::Individual => 'Premio individuale',
        };
    }

    /**
     * Ordine di pubblicazione nel banner pubblico.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Club => 0,
            self::National => 1,
            self::Individual => 2,
        };
    }
}
