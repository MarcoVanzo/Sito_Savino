<?php

namespace App\Enums;

enum StaffType: string
{
    case Tecnico = 'tecnico';
    case Medico = 'medico';
    case Dirigenza = 'dirigenza';

    public function label(): string
    {
        return match ($this) {
            self::Tecnico => 'Staff Tecnico',
            self::Medico => 'Staff Medico',
            self::Dirigenza => 'Dirigenza / Organigramma',
        };
    }
}
