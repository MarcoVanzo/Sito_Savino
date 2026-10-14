<?php

namespace App\Enums;

use App\Models\SiteSetting;
use Filament\Support\Contracts\HasLabel;

/**
 * I livelli di sponsorizzazione realmente in uso sul sito della società.
 *
 * L'ordine dei case è l'ordine predefinito dei gruppi nella pagina pubblica
 * (quello chiesto dalla società il 14/10/2026); la redazione lo cambia dal
 * pannello (Sponsor → «Nomi e ordine delle sezioni»), vedi `ordered()`.
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
    case Health = 'health';
    case Sister = 'sister';
    case Official = 'official';
    case Sustainability = 'sustainability';
    case Water = 'water';
    case Coffee = 'coffee';
    case Supporter = 'supporter';
    case Supplier = 'supplier';
    case Education = 'education';
    case Radio = 'radio';
    case Media = 'media';

    case Gold = 'gold';
    case Silver = 'silver';
    case Standard = 'standard';

    /**
     * I nomi scelti dalla redazione (Sponsor → «Nomi e ordine delle sezioni»), lingua
     * per lingua: `{"it": {"health": "…"}, "en": {…}}`. Un livello senza nome
     * scritto usa `nomePredefinito()`.
     */
    public const CHIAVE_DEI_NOMI = 'sponsor_nomi_delle_sezioni';

    /**
     * L'ordine scelto dalla redazione: elenco dei `value` dei livelli.
     */
    public const CHIAVE_DELL_ORDINE = 'sponsor_ordine_delle_sezioni';

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
            self::Water => 'Official Water',
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
     * Livelli che la redazione può rinominare e ordinare, nell'ordine di
     * pubblicazione: gli storici restano fuori.
     *
     * @return list<self>
     */
    public static function rinominabili(): array
    {
        $rinominabili = [];

        foreach (self::ordered() as $tier) {
            if (! in_array($tier, [self::Gold, self::Silver, self::Standard], true)) {
                $rinominabili[] = $tier;
            }
        }

        return $rinominabili;
    }

    /**
     * Livelli in ordine di pubblicazione: prima quelli nell'ordine salvato
     * dalla redazione, poi quelli che lì mancano (un livello nuovo, o un
     * ordine mai salvato) nell'ordine dei case.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        $salvato = SiteSetting::get(self::CHIAVE_DELL_ORDINE);
        $salvato = is_string($salvato) ? json_decode($salvato, true) : $salvato;

        $ordinati = [];

        foreach (is_array($salvato) ? $salvato : [] as $valore) {
            $tier = is_string($valore) ? self::tryFrom($valore) : null;

            if ($tier !== null && ! in_array($tier, $ordinati, true)) {
                $ordinati[] = $tier;
            }
        }

        foreach (self::cases() as $tier) {
            if (! in_array($tier, $ordinati, true)) {
                $ordinati[] = $tier;
            }
        }

        return $ordinati;
    }
}
