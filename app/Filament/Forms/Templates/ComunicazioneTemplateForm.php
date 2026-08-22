<?php

namespace App\Filament\Forms\Templates;

use App\Filament\Forms\EtichetteDeiCampi;
use Filament\Forms;

/**
 * Le tre schede del form della pagina Comunicazione: accrediti stampa,
 * cartelle stampa e rubrica dei referenti.
 */
class ComunicazioneTemplateForm
{
    /**
     * Hero della pagina e modulo di richiesta accredito.
     *
     * @return array<int, Forms\Components\Tabs\Tab>
     */
    public static function schedaAccrediti(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('Hero & Accrediti')
                ->icon('heroicon-o-newspaper')
                ->schema([
                    Forms\Components\Fieldset::make('Hero')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.hero_badge')
                                ->label(EtichetteDeiCampi::HERO_BADGE)
                                ->placeholder('es. AREA COMUNICAZIONE'),
                            Forms\Components\TextInput::make('content_data.hero_subtitle')
                                ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                                ->placeholder('es. Ufficio Stampa & Media Hub'),
                        ]),

                    Forms\Components\Fieldset::make('Procedura Accrediti Stampa')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.accreditation_badge')
                                ->label('Badge Sezione Accrediti')
                                ->placeholder('es. ACCREDITI'),
                            Forms\Components\TextInput::make('content_data.accreditation_title')
                                ->label('Titolo Sezione Accrediti')
                                ->placeholder('es. Richiesta di Accredito Stampa'),
                            Forms\Components\Textarea::make('content_data.accreditation_text_1')
                                ->label('Testo Accrediti 1')
                                ->rows(3),
                            Forms\Components\Textarea::make('content_data.accreditation_text_2')
                                ->label('Testo Accrediti 2')
                                ->rows(3),
                            // Le tre "fasi della procedura" spiegavano come scrivere
                            // una mail: la richiesta si manda dal modulo nella pagina
                            // e arriva in "Richieste Accrediti". Qui restano le
                            // condizioni, che compaiono accanto al modulo.
                            Forms\Components\Textarea::make('content_data.accreditation_notes')
                                ->label('Condizioni e note')
                                ->helperText('Compaiono accanto al modulo. Lasciando vuoto, il riquadro non viene mostrato.')
                                ->rows(4)
                                ->placeholder("es. Le richieste si chiudono 24 ore prima della gara.\nI posti in tribuna stampa sono limitati."),
                        ])->columns(1),
                ]),
        ];
    }

    /**
     * Elenco delle cartelle stampa scaricabili.
     *
     * @return array<int, Forms\Components\Tabs\Tab>
     */
    public static function schedaCartelleStampa(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('Press Kits')
                ->icon('heroicon-o-folder-arrow-down')
                ->schema([
                    Forms\Components\Fieldset::make('Intestazioni Press Kit')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.press_kit_badge')
                                ->label('Badge Sezione')
                                ->placeholder('es. DOWNLOAD'),
                            Forms\Components\TextInput::make('content_data.press_kit_section_title')
                                ->label('Titolo Sezione Press Kit')
                                ->placeholder('es. Press Kit Ufficiali'),
                        ]),

                    // Quattro caselle fisse ("Foto", "Loghi", "Cartella Stampa",
                    // "Guida Media") non bastano: servono il logo, il brand book e
                    // una cartella per ogni gara del calendario. L'elenco e' libero.
                    Forms\Components\Repeater::make('content_data.press_kits')
                        ->label('Materiale stampa')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Titolo')
                                ->required()
                                ->placeholder('es. Brand Book 2026/2027'),
                            Forms\Components\TextInput::make('icon')
                                ->label('Emoji (opzionale)')
                                ->placeholder('es. 🎨'),
                            Forms\Components\Textarea::make('description')
                                ->label(EtichetteDeiCampi::SHORT_DESCRIPTION)
                                ->rows(2)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('format')
                                ->label('Formato e peso')
                                ->placeholder('es. PDF — 8 MB'),
                            Forms\Components\FileUpload::make('file')
                                ->label('File da scaricare (PDF/ZIP)')
                                ->acceptedFileTypes([EtichetteDeiCampi::PDF_MIME, 'application/zip', 'application/x-zip-compressed'])
                                ->directory('press-kit')
                                ->required()
                                ->preserveFilenames(),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->createItemButtonLabel('Aggiungi materiale')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                ]),
        ];
    }

    /**
     * Rubrica dei referenti per la stampa.
     *
     * @return array<int, Forms\Components\Tabs\Tab>
     */
    public static function schedaContattiMedia(): array
    {
        return [
            Forms\Components\Tabs\Tab::make('Contatti Media')
                ->icon('heroicon-o-users')
                ->schema([
                    Forms\Components\Fieldset::make('Intestazioni Contatti')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.contacts_badge')
                                ->label('Badge Sezione Contatti')
                                ->placeholder('es. CONTATTI'),
                            Forms\Components\TextInput::make('content_data.contacts_section_title')
                                ->label('Titolo Sezione Contatti')
                                ->placeholder('es. Ufficio Stampa & Media Relations'),
                        ]),

                    Forms\Components\Fieldset::make('Contatto 1 (Ufficio Stampa)')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.contact_1_role')
                                        ->label('Ruolo')
                                        ->placeholder('es. Responsabile Ufficio Stampa'),
                                    Forms\Components\TextInput::make('content_data.contact_1_name')
                                        ->label(EtichetteDeiCampi::FULL_NAME)
                                        ->placeholder('es. Stefano Rossi'),
                                    Forms\Components\TextInput::make('content_data.contact_1_email')
                                        ->label('Email')
                                        ->placeholder('es. stampa@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.contact_1_phone')
                                        ->label('Telefono')
                                        ->placeholder('es. +39 055 000 0000'),
                                ]),
                        ]),

                    Forms\Components\Fieldset::make('Contatto 2 (Social Media)')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.contact_2_role')
                                        ->label('Ruolo')
                                        ->placeholder('es. Social Media Specialist'),
                                    Forms\Components\TextInput::make('content_data.contact_2_name')
                                        ->label(EtichetteDeiCampi::FULL_NAME)
                                        ->placeholder('es. Giulia Bianchi'),
                                    Forms\Components\TextInput::make('content_data.contact_2_email')
                                        ->label('Email')
                                        ->placeholder('es. social@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.contact_2_phone')
                                        ->label('Telefono')
                                        ->placeholder('es. +39 055 000 0001'),
                                ]),
                        ]),

                    Forms\Components\Fieldset::make('Contatto 3 (Fotografo Ufficiale)')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.contact_3_role')
                                        ->label('Ruolo')
                                        ->placeholder('es. Fotografo Ufficiale'),
                                    Forms\Components\TextInput::make('content_data.contact_3_name')
                                        ->label(EtichetteDeiCampi::FULL_NAME)
                                        ->placeholder('es. Marco Neri'),
                                    Forms\Components\TextInput::make('content_data.contact_3_email')
                                        ->label('Email')
                                        ->placeholder('es. media@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.contact_3_phone')
                                        ->label('Telefono')
                                        ->placeholder('es. +39 055 000 0002'),
                                ]),
                        ]),
                ]),
        ];
    }
}
