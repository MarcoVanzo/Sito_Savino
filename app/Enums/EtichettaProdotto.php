<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Le etichette che la redazione puo' mettere sulla foto di un prodotto.
 *
 * Il valore e' anche la chiave della traduzione (`shop.badge_<valore>`) e del
 * colore nella card: aggiungendone una va aggiunta in it.json, en.json e in
 * ProductCard.vue.
 */
enum EtichettaProdotto: string implements HasLabel
{
    case Nuovo = 'nuovo';
    case HotSales = 'hot_sales';
    case InOfferta = 'in_offerta';
    case UltimoRimasto = 'ultimo_rimasto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nuovo => 'Nuovo',
            self::HotSales => 'Hot Sales',
            self::InOfferta => 'In offerta',
            self::UltimoRimasto => 'Ultimo rimasto',
        };
    }

    /**
     * Le due etichette che affermano un fatto sul prezzo o sulla giacenza.
     *
     * Un'offerta annunciata senza sconto in corso, o una scarsita' che non c'e',
     * sono pratiche commerciali ingannevoli (Codice del consumo, artt. 21 e 23):
     * queste compaiono solo mentre sono vere, qualunque cosa dica il pannello.
     */
    public function descrizioneNelPannello(): ?string
    {
        return match ($this) {
            self::InOfferta => 'compare solo mentre lo sconto è in corso',
            self::UltimoRimasto => 'compare solo quando resta un pezzo per taglia',
            default => null,
        };
    }
}
