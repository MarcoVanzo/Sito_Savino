<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CompetitionType: string implements HasLabel
{
    case Championship = 'Campionato';
    case CoppaItalia = 'Coppa Italia';
    case ChampionsLeague = 'Champions League';
    case Friendly = 'Amichevole';

    public function getLabel(): string
    {
        return $this->value;
    }
}
