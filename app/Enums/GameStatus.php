<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GameStatus: string implements HasLabel
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Postponed = 'postponed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmata',
            self::InProgress => 'In corso',
            self::Completed => 'Completata',
            self::Postponed => 'Rinviata',
        };
    }
}
