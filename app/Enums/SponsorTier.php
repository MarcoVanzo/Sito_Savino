<?php

namespace App\Enums;

use App\Models\SiteSetting;
use Filament\Support\Contracts\HasLabel;

/**
 * I livelli di sponsorizzazione realmente in uso sul sito della società.
 *
 * L'ordine dei case è l'ordine in cui i gruppi compaiono nella pagina pubblica:
 * cambiarlo qui cambia la pagina, senza toccare il template.
 *
 * `Gold`, `Silver` e `Standard` restano solo per compatibilità con i record
 * creati prima dell'allineamento: non vanno usati per nuovi sponsor.
 */
enum SponsorTier: string implements HasLabel
{
    case Title = 'title';
    case Main = 'main';
    case Premium = 'premium';
    case Mobility = 'mobility';
    case Technical = 'technical';
    case Water = 'water';
    case Sister = 'sister';
    case Health = 'health';
    case Coffee = 'coffee';
    case Sustainability = 'sustainability';
    case Official = 'official';
    case Education = 'education';
    case Supplier = 'supplier';
    case Supporter = 'supporter';
    case Radio = 'radio';
    case Media = 'media';

    case Gold = 'gold';
    case Silver = 'silver';
    case Standard = 'standard';

    /**
     * I nomi scelti dalla redazione (Sponsor → «Nomi delle sezioni»), lingua
     * per lingua: `{"it": {"health": "…"}, "en": {…}}`. Un livello senza nome
     * scritto usa `nomePredefinito()`.
     */
    public const CHIAVE_DEI_NOMI = 'sponsor_nomi_delle_sezioni';

    public function getLabel(): string
    {
        $nomi = SiteSetting::get(self::CHIAVE_DEI_NOMI);
        $nome = is_array($nomi) ? trim((string) ($nomi[$this->value] ?? '')) : '';

        return $nome !== '' ? $nome : $this->nomePredefinito();
    }

    /**
     * Il nome del livello quando la redazione non ne ha scritto uno, nella
     * lingua data (quella in uso se manca).
     */
    public function nomePredefinito(?string $locale = null): string
    {
        return match ($this) {
            self::Title => 'Title Sponsor',
            self::Main => 'Main Sponsor',
            self::Premium => 'Premium Partner',
            self::Mobility => 'Mobility Partner',
            self::Technical => __('Sponsor Tecnico', [], $locale),
            self::Water => __('Acqua Ufficiale', [], $locale),
            self::Sister => 'Sister Companies',
            self::Health => 'Health & Performance Partner',
            self::Coffee => 'Official Coffee',
            self::Sustainability => 'Sustainability Partner',
            self::Official => 'Official Partner',
            self::Education => 'Institutional Education Partner',
            self::Supplier => 'Official Supplier',
            self::Supporter => 'Official Supporter',
            self::Radio => 'Official Radio',
            self::Media => 'Media Partner',
            self::Gold => 'Gold Partner (storico)',
            self::Silver => 'Silver Partner (storico)',
            self::Standard => 'Supporter (storico)',
        };
    }

    /**
     * Peso visivo del livello nella pagina pubblica: decide quanto grande
     * viene mostrato il logo e quante colonne ha la griglia.
     */
    public function size(): string
    {
        return match ($this) {
            self::Title => 'hero',
            self::Main, self::Gold => 'large',
            self::Premium, self::Mobility, self::Technical, self::Water, self::Silver => 'medium',
            default => 'small',
        };
    }

    /**
     * Livelli che la redazione può rinominare: gli storici restano fuori.
     *
     * @return list<self>
     */
    public static function rinominabili(): array
    {
        $rinominabili = [];

        foreach (self::cases() as $tier) {
            if (! in_array($tier, [self::Gold, self::Silver, self::Standard], true)) {
                $rinominabili[] = $tier;
            }
        }

        return $rinominabili;
    }

    /**
     * Livelli in ordine di pubblicazione.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}
