<?php

namespace App\Filament\Forms\Templates;

use App\Filament\Forms\EtichetteDeiCampi;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Le tre schede del form della pagina Comunicazione: accrediti stampa,
 * cartelle stampa e rubrica dei referenti.
 */
class ComunicazioneTemplateForm
{
    /**
     * Hero della pagina e modulo di richiesta accredito.
     *
     * @return array<int, Tab>
     */
    public static function schedaAccrediti(): array
    {
        return [
            Tab::make('Hero & Accrediti')
                ->icon('heroicon-o-newspaper')
                ->schema([
                    Fieldset::make('Hero')
                        ->schema([
                            TextInput::make('content_data.hero_badge')
                                ->label(EtichetteDeiCampi::HERO_BADGE)
                                ->placeholder('es. AREA COMUNICAZIONE'),
                            TextInput::make('content_data.hero_subtitle')
                                ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                                ->placeholder('es. Ufficio Stampa & Media Hub'),
                        ]),

                    Fieldset::make('Procedura Accrediti Stampa')
                        ->schema([
                            TextInput::make('content_data.accreditation_badge')
                                ->label('Badge Sezione Accrediti')
                                ->placeholder('es. ACCREDITI'),
                            TextInput::make('content_data.accreditation_title')
                                ->label('Titolo Sezione Accrediti')
                                ->placeholder('es. Richiesta di Accredito Stampa'),
                            Textarea::make('content_data.accreditation_text_1')
                                ->label('Testo Accrediti 1')
                                ->rows(3),
                            Textarea::make('content_data.accreditation_text_2')
                                ->label('Testo Accrediti 2')
                                ->rows(3),
                            // Le tre "fasi della procedura" spiegavano come scrivere
                            // una mail: la richiesta si manda dal modulo nella pagina
                            // e arriva in "Richieste Accrediti". Qui restano le
                            // condizioni, che compaiono accanto al modulo.
                            Textarea::make('content_data.accreditation_notes')
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
     * @return array<int, Tab>
     */
    public static function schedaCartelleStampa(): array
    {
        return [
            Tab::make('Press Kits')
                ->icon('heroicon-o-folder-arrow-down')
                ->schema([
                    Fieldset::make('Intestazioni Press Kit')
                        ->schema([
                            TextInput::make('content_data.press_kit_badge')
                                ->label('Badge Sezione')
                                ->placeholder('es. DOWNLOAD'),
                            TextInput::make('content_data.press_kit_section_title')
                                ->label('Titolo Sezione Press Kit')
                                ->placeholder('es. Press Kit Ufficiali'),
                        ]),

                    // Quattro caselle fisse ("Foto", "Loghi", "Cartella Stampa",
                    // "Guida Media") non bastano: servono il logo, il brand book e
                    // una cartella per ogni gara del calendario. L'elenco e' libero.
                    Repeater::make('content_data.press_kits')
                        ->label('Materiale stampa')
                        ->schema([
                            TextInput::make('title')
                                ->label('Titolo')
                                ->required()
                                ->placeholder('es. Brand Book 2026/2027'),
                            TextInput::make('icon')
                                ->label('Emoji (opzionale)')
                                ->placeholder('es. 🎨'),
                            Textarea::make('description')
                                ->label(EtichetteDeiCampi::SHORT_DESCRIPTION)
                                ->rows(2)
                                ->columnSpanFull(),
                            TextInput::make('format')
                                ->label('Formato e peso')
                                ->placeholder('es. PDF — 8 MB'),
                            FileUpload::make('file')
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
     * @return array<int, Tab>
     */
    public static function schedaContattiMedia(): array
    {
        return [
            Tab::make('Contatti Media')
                ->icon('heroicon-o-users')
                ->schema([
                    Fieldset::make('Intestazioni Contatti')
                        ->schema([
                            TextInput::make('content_data.contacts_badge')
                                ->label('Badge Sezione Contatti')
                                ->placeholder('es. CONTATTI'),
                            TextInput::make('content_data.contacts_section_title')
                                ->label('Titolo Sezione Contatti')
                                ->placeholder('es. Ufficio Stampa & Media Relations'),
                        ]),

                    self::contattoMedia(1, 'Ufficio Stampa', [
                        'role' => 'es. Responsabile Ufficio Stampa',
                        'name' => 'es. Stefano Rossi',
                        'email' => 'es. stampa@savinodelbenevolley.it',
                        'phone' => 'es. +39 055 000 0000',
                    ]),

                    self::contattoMedia(2, 'Social Media', [
                        'role' => 'es. Social Media Specialist',
                        'name' => 'es. Giulia Bianchi',
                        'email' => 'es. social@savinodelbenevolley.it',
                        'phone' => 'es. +39 055 000 0001',
                    ]),

                    self::contattoMedia(3, 'Fotografo Ufficiale', [
                        'role' => 'es. Fotografo Ufficiale',
                        'name' => 'es. Marco Neri',
                        'email' => 'es. media@savinodelbenevolley.it',
                        'phone' => 'es. +39 055 000 0002',
                    ]),
                ]),
        ];
    }

    /**
     * Una scheda della rubrica stampa. I tre referenti hanno gli stessi quattro
     * campi e cambiano solo di numero: erano tre blocchi identici in fila.
     *
     * @param  array{role: string, name: string, email: string, phone: string}  $esempi
     */
    private static function contattoMedia(int $numero, string $titolo, array $esempi): Fieldset
    {
        return Fieldset::make("Contatto {$numero} ({$titolo})")
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make("content_data.contact_{$numero}_role")
                            ->label('Ruolo')
                            ->placeholder($esempi['role']),
                        TextInput::make("content_data.contact_{$numero}_name")
                            ->label(EtichetteDeiCampi::FULL_NAME)
                            ->placeholder($esempi['name']),
                        TextInput::make("content_data.contact_{$numero}_email")
                            ->label('Email')
                            ->placeholder($esempi['email']),
                        TextInput::make("content_data.contact_{$numero}_phone")
                            ->label('Telefono')
                            ->placeholder($esempi['phone']),
                    ]),
            ]);
    }
}
