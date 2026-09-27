<?php

namespace App\Enums;

/**
 * I modelli con cui una pagina del CMS arriva sul sito: il valore e' il nome
 * del componente Vue sotto `resources/js/Pages/`.
 *
 * E' l'unico elenco. Prima ne esistevano quattro scritti a mano — le opzioni
 * del pannello, le due liste che decidono quali sezioni del modulo mostrare e
 * la whitelist di `PageController` — e non combaciavano: il pannello offriva
 * `Public/Shop`, che non ha componente, e la pagina ricadeva in silenzio sulla
 * pagina generica. Un modello nuovo si aggiunge qui e basta.
 *
 * L'ordine dei case e' quello della tendina del pannello.
 */
enum PageTemplate: string
{
    case Predefinito = 'Default';
    case Organigramma = 'Public/Societa/Organigramma';
    case Storia = 'Public/Societa/Storia';
    case Palazzetto = 'Public/Societa/Palazzetto';
    case Safeguarding = 'Public/Societa/Safeguarding';
    case Roster = 'Public/Roster';
    case Ticketing = 'Public/Ticketing';
    case ClubRace = 'Public/ClubRace';
    case Convenzioni = 'Public/Convenzioni';
    case Sponsor = 'Public/Sponsor';
    case Youth = 'Public/Youth';
    case Affiliazioni = 'Public/Affiliazioni';
    case SummerCamp = 'Public/SummerCamp';
    case TalentDay = 'Public/TalentDay';
    case Sociale = 'Public/Sociale';
    case Comunicazione = 'Public/Comunicazione';
    case Stagione = 'Public/Stagione';
    case ContentPage = 'Public/ContentPage';

    // Pagine con una rotta propria: il sito le sa mostrare, ma la redazione
    // non le assegna ad altre pagine.
    case Home = 'Public/Home';
    case Risultati = 'Public/Risultati';
    case Gallery = 'Public/Gallery';
    case Staff = 'Public/Staff';
    case Contatti = 'Public/Contatti';

    /**
     * Il componente Vue da mostrare. `Default` e' il valore storico del
     * "modello predefinito" e vale la pagina generica.
     */
    public function componente(): string
    {
        return $this === self::Predefinito ? self::ContentPage->value : $this->value;
    }

    /**
     * Il nome nella tendina del pannello; null se la redazione non lo sceglie.
     */
    public function etichetta(): ?string
    {
        return match ($this) {
            self::Predefinito => 'Template Predefinito',
            self::Organigramma => 'Società (Organigramma)',
            self::Storia => 'Società - Storia',
            self::Palazzetto => 'Società - Palazzetto',
            self::Safeguarding => 'Società - Safeguarding',
            self::Roster => 'Roster',
            self::Ticketing => 'Biglietteria',
            self::ClubRace => 'SDB Volley Club Race',
            self::Convenzioni => 'Convenzioni per gli abbonati',
            self::Sponsor => 'Sponsor',
            self::Youth => 'Settore Giovanile',
            self::Affiliazioni => 'Progetto Affiliazioni',
            self::SummerCamp => 'Summer Camp',
            self::TalentDay => 'Talent Day & Recruiting',
            self::Sociale => 'Progetti Sociali',
            self::Comunicazione => 'Comunicazione',
            self::Stagione => 'Stagione',
            self::ContentPage => 'Pagina Contenuto',
            self::Home, self::Risultati, self::Gallery, self::Staff, self::Contatti => null,
        };
    }

    /**
     * Le voci della tendina del pannello.
     *
     * @return array<string, string>
     */
    public static function opzioni(): array
    {
        $opzioni = [];

        foreach (self::cases() as $modello) {
            $etichetta = $modello->etichetta();

            if ($etichetta !== null) {
                $opzioni[$modello->value] = $etichetta;
            }
        }

        return $opzioni;
    }

    /**
     * Il componente per il valore salvato su `pages.template`: un valore
     * vuoto o sconosciuto vale la pagina generica, che mostra il contenuto
     * dell'editor. E' anche la difesa contro un nome arbitrario scritto nel
     * database, che altrimenti arriverebbe a Inertia.
     */
    public static function componenteDi(?string $valore): string
    {
        return (self::tryFrom((string) $valore) ?? self::ContentPage)->componente();
    }

    /**
     * Per le condizioni `visible()` del pannello: vero se il modello scelto
     * nel modulo e' uno di questi.
     */
    public static function scelto(?string $valore, self ...$modelli): bool
    {
        return in_array(self::tryFrom((string) $valore), $modelli, true);
    }
}
