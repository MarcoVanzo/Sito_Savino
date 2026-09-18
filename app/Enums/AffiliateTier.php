<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * I livelli del progetto affiliazioni.
 *
 * Come per gli sponsor, l'ordine dei case e' l'ordine in cui i gruppi compaiono
 * nella pagina pubblica: sono i tre del sito precedente, e cambiarli qui li
 * cambia online senza toccare il template.
 *
 * Le etichette del pannello sono in italiano; quelle pubblicate arrivano dalle
 * traduzioni del frontend, perche' la pagina esiste anche in inglese.
 */
enum AffiliateTier: string implements HasLabel
{
    case Main = 'main';
    case Official = 'official';
    case Affiliated = 'affiliated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Main => 'Main Partner',
            self::Official => 'Partner Ufficiale',
            self::Affiliated => 'Società Affiliata',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function opzioni(): array
    {
        $opzioni = [];

        foreach (self::cases() as $livello) {
            $opzioni[$livello->value] = $livello->getLabel();
        }

        return $opzioni;
    }

    /**
     * @return list<string>
     */
    public static function ordine(): array
    {
        return array_map(fn (self $livello) => $livello->value, self::cases());
    }
}
