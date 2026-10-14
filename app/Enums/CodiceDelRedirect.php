<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cosa risponde un vecchio indirizzo mappato a mano.
 *
 * 301 porta alla destinazione; 410 dice a Google che il contenuto non c'è più
 * e non tornerà, così lo toglie dall'indice prima di un 404.
 */
enum CodiceDelRedirect: string implements HasLabel
{
    case Permanente = '301';
    case Rimosso = '410';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanente => '301 · spostato',
            self::Rimosso => '410 · rimosso',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Permanente => 'success',
            self::Rimosso => 'gray',
        };
    }

    public function codiceHttp(): int
    {
        return (int) $this->value;
    }

    public function vuoleLaDestinazione(): bool
    {
        return $this === self::Permanente;
    }
}
