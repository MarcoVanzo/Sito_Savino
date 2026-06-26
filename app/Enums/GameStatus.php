<?php

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Postponed = 'postponed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmata',
            self::InProgress => 'In corso',
            self::Completed => 'Completata',
            self::Postponed => 'Rinviata',
        };
    }
}
