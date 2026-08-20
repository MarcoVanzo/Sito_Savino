<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CompetitionType: string implements HasLabel
{
    case Championship = 'Campionato';
    case CoppaItalia = 'Coppa Italia';
    // Coppa Italia e Playoff stavano insieme in una pagina sola, ma sono due
    // competizioni diverse con qualificazioni diverse: la redazione le vuole
    // separate anche quando una delle due non e' ancora in calendario.
    case Playoff = 'Playoff';
    case ChampionsLeague = 'Champions League';
    case Friendly = 'Amichevole';

    public function getLabel(): string
    {
        return $this->value;
    }
}
