<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Gli eventi che il sito conta per il report degli sponsor della diretta. */
enum EventoDellaDiretta: string implements HasLabel
{
    case PrerollAvviato = 'preroll_avviato';
    case PrerollCompletato = 'preroll_completato';
    case PrerollSaltato = 'preroll_saltato';
    case CicloMostrato = 'ciclo_mostrato';
    case PausaMostrata = 'pausa_mostrata';
    case Clic = 'clic';

    public function getLabel(): string
    {
        return match ($this) {
            self::PrerollAvviato => 'Pre-roll avviati',
            self::PrerollCompletato => 'Pre-roll visti fino in fondo',
            self::PrerollSaltato => 'Pre-roll saltati',
            self::CicloMostrato => 'Cornici del ciclo automatico',
            self::PausaMostrata => 'Cornici mandate dalla regia',
            self::Clic => 'Clic',
        };
    }
}
