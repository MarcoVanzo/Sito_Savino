<?php

namespace App\Enums;

enum CompetitionType: string
{
    case Championship = 'Campionato';
    case CoppaItalia = 'Coppa Italia';
    case ChampionsLeague = 'Champions League';
    case Friendly = 'Amichevole';

    public function label(): string
    {
        return $this->value;
    }
}
