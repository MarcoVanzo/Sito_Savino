<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cosa sta facendo la diretta di una gara.
 *
 * - `InOnda`: la diretta va, e la cornice compare da sola secondo il ciclo;
 * - `Pausa`: la regia sta mandando uno spot (prevale sul ciclo) fino a
 *   `pausa_fino_alle`;
 * - `Inattiva`: «Spegni tutto», nessuno sponsor durante la gara.
 */
enum StatoDellaDiretta: string implements HasLabel
{
    case Inattiva = 'inattiva';
    case InOnda = 'in_onda';
    case Pausa = 'pausa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inattiva => 'Sponsor spenti',
            self::InOnda => 'In onda',
            self::Pausa => 'Spot della regia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Inattiva => 'gray',
            self::InOnda => 'success',
            self::Pausa => 'warning',
        };
    }
}
