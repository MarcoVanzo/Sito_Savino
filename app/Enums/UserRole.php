<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore',
            self::Editor => 'Editore',
            self::User => 'Utente',
        };
    }
}
