<?php

namespace App\Filament\Forms\Templates;

use App\Filament\Forms\CampiDelleStatistiche;
use App\Filament\Forms\EtichetteDeiCampi;
use Filament\Forms;

/**
 * Le due schede del form della pagina Settore Giovanile.
 *
 * "Valori" e "Squadre" non ci sono piu': la redazione ha chiesto di togliere
 * quelle due sezioni dalla pagina, e un campo che non si vede online non deve
 * restare nel pannello a far credere il contrario.
 *
 * Stanno qui e non in PageTemplateForms perche' quella classe raccoglie tutti i
 * template del CMS: aggiungendoci anche le schede di due template lunghi era
 * diventata un elenco di ventisei metodi in cui non si trovava piu' niente.
 */
class YouthTemplateForm
{
    /**
     * Hero, introduzione e i numeri del vivaio.
     *
     * @return array<int, Forms\Components\Tabs\Tab>
     */
    public static function schedaInfoEStatistiche(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('Info & Statistiche')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Forms\Components\Fieldset::make('Hero')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.hero_subtitle')
                                ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                                ->placeholder('es. LINEA VERDE'),
                            Forms\Components\Textarea::make('content_data.hero_description')
                                ->label(EtichetteDeiCampi::HERO_DESCRIPTION)
                                ->placeholder('es. Il futuro della pallavolo nasce dalle nostre giovani atlete...'),
                        ]),

                    Forms\Components\Fieldset::make('Introduzione')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.intro_label')
                                ->label('Etichetta Intro')
                                ->placeholder('es. IL NOSTRO IMPEGNO'),
                            Forms\Components\TextInput::make('content_data.intro_title')
                                ->label('Titolo Intro')
                                ->placeholder('es. Coltiviamo Talenti, Cresciamo Persone'),
                            Forms\Components\Textarea::make('content_data.intro_paragraph_1')
                                ->label('Paragrafo Intro 1')
                                ->rows(3),
                            Forms\Components\Textarea::make('content_data.intro_paragraph_2')
                                ->label('Paragrafo Intro 2')
                                ->rows(3),
                        ])->columns(1),

                    Forms\Components\Fieldset::make('Numeri del Settore Giovanile')
                        ->schema([
                            CampiDelleStatistiche::griglia([
                                ['valore' => 'content_data.stat_athletes', 'etichetta' => 'content_data.stat_athletes_label',
                                    'nome' => 'Atlete', 'esempioValore' => 'es. 70+', 'esempioEtichetta' => 'es. Atlete Tesserate'],
                                ['valore' => 'content_data.stat_categories', 'etichetta' => 'content_data.stat_categories_label',
                                    'nome' => 'Categorie', 'esempioValore' => 'es. 4', 'esempioEtichetta' => 'es. Categorie d\'Età'],
                                ['valore' => 'content_data.stat_coaches', 'etichetta' => 'content_data.stat_coaches_label',
                                    'nome' => 'Allenatori', 'esempioValore' => 'es. 12', 'esempioEtichetta' => 'es. Tecnici Qualificati'],
                                ['valore' => 'content_data.stat_years', 'etichetta' => 'content_data.stat_years_label',
                                    'nome' => 'Anni', 'esempioValore' => 'es. 15+', 'esempioEtichetta' => 'es. Anni di Attività'],
                            ], 4),
                        ]),
                ]),
        ];
    }

    /**
     * Talent day e recruiting.
     *
     * @return array<int, Forms\Components\Tabs\Tab>
     */
    public static function schedaScouting(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('Scouting Giovanile')
                ->icon('heroicon-o-magnifying-glass')
                ->schema([
                    Forms\Components\TextInput::make('content_data.scouting_label')
                        ->label('Etichetta Scouting')
                        ->placeholder('es. TALENT SCOUTING'),
                    Forms\Components\TextInput::make('content_data.scouting_title')
                        ->label('Titolo Sezione Scouting')
                        ->placeholder('es. Diventa una di Noi'),
                    Forms\Components\Textarea::make('content_data.scouting_description')
                        ->label('Descrizione Scouting')
                        ->rows(3),
                    Forms\Components\Textarea::make('content_data.scouting_info')
                        ->label('Informazioni di Contatto Scouting')
                        ->rows(2),
                    Forms\Components\TextInput::make('content_data.scouting_cta_primary')
                        ->label('Testo del Pulsante')
                        ->placeholder('es. Scrivi al settore giovanile'),
                    // Il pulsante e' uno solo e apre la posta: se questo campo
                    // resta vuoto vale l'indirizzo in Impostazioni -> Contatti,
                    // e senza nessuno dei due il pulsante non compare.
                    Forms\Components\TextInput::make('content_data.scouting_email')
                        ->label('Email del settore giovanile')
                        ->email()
                        ->placeholder('es. giovanile@savinodelbenevolley.it'),
                ]),
        ];
    }
}
