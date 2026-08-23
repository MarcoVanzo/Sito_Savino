<?php

namespace App\Filament\Forms\Templates;

use App\Filament\Forms\EtichetteDeiCampi;
use Filament\Forms;

/**
 * Le tre schede del form della pagina Settore Giovanile.
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
                            Forms\Components\Grid::make(4)
                                ->schema([
                                    Forms\Components\Group::make([
                                        Forms\Components\TextInput::make('content_data.stat_athletes')
                                            ->label('Valore Atlete')
                                            ->placeholder('es. 70+'),
                                        Forms\Components\TextInput::make('content_data.stat_athletes_label')
                                            ->label('Etichetta Atlete')
                                            ->placeholder('es. Atlete Tesserate'),
                                    ]),
                                    Forms\Components\Group::make([
                                        Forms\Components\TextInput::make('content_data.stat_categories')
                                            ->label('Valore Categorie')
                                            ->placeholder('es. 4'),
                                        Forms\Components\TextInput::make('content_data.stat_categories_label')
                                            ->label('Etichetta Categorie')
                                            ->placeholder('es. Categorie d\'Età'),
                                    ]),
                                    Forms\Components\Group::make([
                                        Forms\Components\TextInput::make('content_data.stat_coaches')
                                            ->label('Valore Allenatori')
                                            ->placeholder('es. 12'),
                                        Forms\Components\TextInput::make('content_data.stat_coaches_label')
                                            ->label('Etichetta Allenatori')
                                            ->placeholder('es. Tecnici Qualificati'),
                                    ]),
                                    Forms\Components\Group::make([
                                        Forms\Components\TextInput::make('content_data.stat_years')
                                            ->label('Valore Anni')
                                            ->placeholder('es. 15+'),
                                        Forms\Components\TextInput::make('content_data.stat_years_label')
                                            ->label('Etichetta Anni')
                                            ->placeholder('es. Anni di Attività'),
                                    ]),
                                ]),
                        ]),
                ]),
        ];
    }

    /**
     * I valori del settore e le squadre iscritte.
     *
     * @return array<int, Forms\Components\Tabs\Tab>
     */
    public static function schedaValoriESquadre(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('Valori & Squadre')
                ->icon('heroicon-o-academic-cap')
                ->schema([
                    Forms\Components\TextInput::make('content_data.values_title')
                        ->label('Titolo Sezione Valori')
                        ->placeholder('es. I Nostri Capisaldi'),

                    Forms\Components\Repeater::make('content_data.values')
                        ->label('Valori del Settore Giovanile')
                        ->schema([
                            Forms\Components\Select::make('icon')
                                ->label('Icona')
                                ->options([
                                    'star' => 'Stella ⭐',
                                    'heart' => 'Cuore ❤️',
                                    'trophy' => 'Trofeo 🏆',
                                    'users' => 'Utenti 👥',
                                    'shield-check' => 'Scudo 🛡️',
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('title')
                                ->label('Titolo Valore')
                                ->required(),
                            Forms\Components\Textarea::make('description')
                                ->label('Descrizione Valore')
                                ->required()
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->createItemButtonLabel('Aggiungi Valore')
                        ->collapsible(),

                    Forms\Components\TextInput::make('content_data.teams_title')
                        ->label('Titolo Sezione Squadre')
                        ->placeholder('es. Le Nostre Selezioni'),

                    Forms\Components\Repeater::make('content_data.youth_teams')
                        ->label('Squadre Giovanili')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome Squadra (es. Under 18)')
                                ->required(),
                            Forms\Components\TextInput::make('category')
                                ->label('Categoria (es. Serie C / Regionale)')
                                ->required(),
                            Forms\Components\TextInput::make('coach')
                                ->label('Primo Allenatore')
                                ->required(),
                            Forms\Components\TextInput::make('training')
                                ->label('Orari Allenamenti')
                                ->required()
                                ->placeholder('es. Lun-Mer-Ven 16:00-18:00'),
                            Forms\Components\TextInput::make('players')
                                ->label('Numero Atlete')
                                ->placeholder('es. 14'),
                            Forms\Components\Select::make('color')
                                ->label('Colore Tema Card')
                                ->options([
                                    'savino-blue' => 'Blu Savino',
                                    'savino-fucsia' => 'Fucsia Savino',
                                    'savino-red' => 'Rosso Savino',
                                    'savino-pink' => 'Rosa Savino',
                                ])
                                ->required(),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->createItemButtonLabel('Aggiungi Squadra')
                        ->collapsible(),
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
