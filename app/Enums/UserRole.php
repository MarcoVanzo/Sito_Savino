<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Editor = 'editor';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore',
            self::Editor => 'Editore',
            self::User => 'Utente',
        };
    }
}
