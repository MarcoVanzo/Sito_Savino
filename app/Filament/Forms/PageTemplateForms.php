<?php

namespace App\Filament\Forms;

use Filament\Forms;

class PageTemplateForms
{
    /**
     * Restituisce i campi specifici per il template "Società"
     */
    public static function getSocietaSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.hero_subheading')
                ->label('Sottotitolo Hero')
                ->default('Dal 1982'),
            Forms\Components\Textarea::make('content_data.hero_description')
                ->label('Descrizione Hero'),
            Forms\Components\TextInput::make('content_data.storia_title')
                ->label('Titolo Storia'),
            Forms\Components\TagsInput::make('content_data.storia_paragraphs')
                ->label('Paragrafi Storia (Premi Invio per separare)'),
            Forms\Components\TextInput::make('content_data.storia_years')
                ->label('Anni di Storia'),
            Forms\Components\TextInput::make('content_data.org_title')
                ->label('Titolo Organigramma'),
            Forms\Components\TextInput::make('content_data.palazzetto_title')
                ->label('Nome Palazzetto'),
            Forms\Components\Textarea::make('content_data.palazzetto_description')
                ->label('Descrizione Palazzetto'),
            Forms\Components\TextInput::make('content_data.palazzetto_capacity')
                ->label('Capienza Palazzetto'),
            Forms\Components\TextInput::make('content_data.palazzetto_homologation')
                ->label('Omologazione Palazzetto'),
            Forms\Components\TextInput::make('content_data.palazzetto_address')
                ->label('Indirizzo Palazzetto'),
        ];
    }

    /**
     * Restituisce i campi specifici per il template "Biglietteria"
     */
    public static function getTicketingSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.hero_label')
                ->label('Etichetta Hero'),
            Forms\Components\Textarea::make('content_data.hero_subtitle')
                ->label('Sottotitolo Hero'),
            Forms\Components\TextInput::make('content_data.plans_heading')
                ->label('Titolo Sezione Abbonamenti'),
            Forms\Components\TextInput::make('content_data.popular_badge')
                ->label('Testo Badge "Più Popolare"'),
            Forms\Components\Repeater::make('content_data.plans')
                ->label('Piani e Abbonamenti')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nome Piano')->required(),
                    Forms\Components\TextInput::make('price')->label('Prezzo (€)')->required(),
                    Forms\Components\TextInput::make('period')->label('Periodo (es. a partita, stagione)')->required(),
                    Forms\Components\TagsInput::make('features')->label('Vantaggi (Premi invio)'),
                    Forms\Components\Toggle::make('highlight')->label('Evidenziato (Più Popolare)'),
                    Forms\Components\TextInput::make('cta')->label('Testo Pulsante (es. Acquista)'),
                ])->columns(2)->columnSpanFull(),
            Forms\Components\TextInput::make('content_data.info_heading')
                ->label('Titolo Sezione Info'),
            Forms\Components\TextInput::make('content_data.online_title')
                ->label('Titolo Info Online'),
            Forms\Components\Textarea::make('content_data.online_description')
                ->label('Descrizione Info Online'),
            Forms\Components\TextInput::make('content_data.boxoffice_title')
                ->label('Titolo Info Botteghino'),
            Forms\Components\Textarea::make('content_data.boxoffice_description')
                ->label('Descrizione Info Botteghino'),
        ];
    }

    /**
     * Restituisce i campi specifici per il template "Società - Storia"
     */
    public static function getStoriaSchema(): array
    {
        return [
            Forms\Components\Repeater::make('content_data.timeline')
                ->label('Tappe Fondamentali (Timeline)')
                ->schema([
                    Forms\Components\TextInput::make('year')
                        ->label('Anno')
                        ->required()
                        ->placeholder('es. 1982'),
                    Forms\Components\TextInput::make('title')
                        ->label('Titolo della Tappa')
                        ->required()
                        ->placeholder('es. Le Origini'),
                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione dettagliata')
                        ->required()
                        ->placeholder('Inserisci la descrizione della tappa...'),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->createItemButtonLabel('Aggiungi Tappa'),
        ];
    }

    /**
     * Restituisce i campi specifici per il template "Società - Palazzetto"
     */
    public static function getPalazzettoSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.venue_name')
                ->label('Nome della struttura')
                ->placeholder('es. Palazzo Wanny'),
            Forms\Components\TextInput::make('content_data.venue_address')
                ->label('Indirizzo completo')
                ->placeholder('es. Via del Tridente, 5 — 50127 Firenze (FI)'),
            Forms\Components\TextInput::make('content_data.maps_link')
                ->label('Link Google Maps (Pulsante "Apri su Maps")')
                ->placeholder('es. https://maps.app.goo.gl/...'),
            Forms\Components\TextInput::make('content_data.maps_iframe_src')
                ->label('URL Iframe Mappa Google (Src embed)')
                ->placeholder('es. https://www.google.com/maps/embed?...'),
            Forms\Components\Repeater::make('content_data.services')
                ->label('Servizi della struttura')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome Servizio')
                        ->required()
                        ->placeholder('es. Capienza 4000 Posti'),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icona (SVG Path d-attribute o nome)')
                        ->placeholder('es. M17 20h5v-2...'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->createItemButtonLabel('Aggiungi Servizio'),
        ];
    }

    /**
     * Restituisce i campi specifici per il template "Società - Safeguarding"
     */
    public static function getSafeguardingSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.report_title')
                ->label('Titolo Sezione Segnalazioni')
                ->placeholder('es. Segnalazioni'),
            Forms\Components\Textarea::make('content_data.report_description')
                ->label('Descrizione Sezione Segnalazioni')
                ->placeholder('es. Per segnalare comportamenti non conformi...'),
            Forms\Components\TextInput::make('content_data.report_email')
                ->label('Email per Segnalazioni')
                ->placeholder('es. safeguarding@savinodelbenevolley.it'),
            Forms\Components\Repeater::make('content_data.documents')
                ->label('Documenti Ufficiali scaricabili')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Titolo Documento')
                        ->required()
                        ->placeholder('es. Codice di Condotta a Tutela dei Minori'),
                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione breve')
                        ->placeholder('es. Linee guida specifiche per garantire un ambiente sportivo sicuro...'),
                    Forms\Components\TextInput::make('file')
                        ->label('URL File / Allegato (es. /storage/...)')
                        ->required()
                        ->placeholder('Carica il file o inserisci il link...'),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icona SVG (Opzionale)')
                        ->placeholder('es. M12 4.354a4...'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->createItemButtonLabel('Aggiungi Documento'),
        ];
    }

    /**
     * Restituisce i campi specifici per il template "Contatti"
     */
    public static function getContattiSchema(): array
    {
        return [
            Forms\Components\Tabs::make('ContattiPageTabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Testi della Pagina')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Fieldset::make('Intestazione (Hero)')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                                        ->label('Sottotitolo')
                                        ->placeholder('es. Dal 1982'),
                                    Forms\Components\Textarea::make('content_data.hero_description')
                                        ->label('Descrizione Introduttiva')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])->columns(2),

                            Forms\Components\Fieldset::make('Modulo di Contatto')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.form_title')
                                        ->label('Titolo del Modulo')
                                        ->placeholder('es. Scrivici'),
                                    Forms\Components\TextInput::make('content_data.form_success_message')
                                        ->label('Messaggio di Conferma Invio')
                                        ->placeholder('es. Messaggio inviato con successo!'),
                                ])->columns(2),

                            Forms\Components\Fieldset::make('Titoli Mappa')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.map_title')
                                        ->label('Titolo Sezione Mappa')
                                        ->placeholder('es. Sede Legale'),
                                    Forms\Components\TextInput::make('content_data.map_address')
                                        ->label('Testo Indirizzo (Sotto al titolo)')
                                        ->placeholder('es. Via Benozzo Gozzoli, 5/6, Scandicci'),
                                ])->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make('Sede & Dati Societari')
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Forms\Components\Fieldset::make('Recapiti Generali')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.email')
                                        ->label('Email Principale')
                                        ->email()
                                        ->placeholder('es. info@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.pec')
                                        ->label('Indirizzo PEC')
                                        ->email()
                                        ->placeholder('es. savinodelbenevolley@pec.it'),
                                    Forms\Components\TextInput::make('content_data.phone')
                                        ->label('Telefono Sede')
                                        ->placeholder('es. 055 721503'),
                                    Forms\Components\TextInput::make('content_data.office_hours')
                                        ->label('Orari di Apertura')
                                        ->placeholder('es. Lun-Ven: 09:00-18:00'),
                                ])->columns(2),

                            Forms\Components\Fieldset::make('Ubicazione Sede')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.address')
                                        ->label('Indirizzo')
                                        ->placeholder('es. Palazzo Wanny, Via Allende 10'),
                                    Forms\Components\TextInput::make('content_data.city')
                                        ->label('Città / Località')
                                        ->placeholder('es. Firenze'),
                                ])->columns(2),

                            Forms\Components\Fieldset::make('Dati Fiscali e Sportivi')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.legal_piva')
                                        ->label('Partita IVA')
                                        ->placeholder('es. 06271460484'),
                                    Forms\Components\TextInput::make('content_data.legal_cf')
                                        ->label('Codice Fiscale')
                                        ->placeholder('es. 94217750481'),
                                    Forms\Components\TextInput::make('content_data.legal_fipav')
                                        ->label('Codice Affiliazione FIPAV')
                                        ->placeholder('es. 100470331'),
                                    Forms\Components\TextInput::make('content_data.legal_sdi')
                                        ->label('Codice Univoco (SDI)')
                                        ->placeholder('es. KRRH6B9'),
                                ])->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make('Email Dipartimenti')
                        ->icon('heroicon-o-envelope-open')
                        ->schema([
                            Forms\Components\Fieldset::make('Indirizzi Email Specifici')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.press_email')
                                        ->label('Ufficio Stampa')
                                        ->email()
                                        ->placeholder('es. stampa@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.social_email')
                                        ->label('Social Media')
                                        ->email()
                                        ->placeholder('es. social@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.media_email')
                                        ->label('Media & Accrediti')
                                        ->email()
                                        ->placeholder('es. media@savinodelbenevolley.it'),
                                    Forms\Components\TextInput::make('content_data.youth_email')
                                        ->label('Settore Giovanile')
                                        ->email()
                                        ->placeholder('es. giovanili@savinodelbenescandicci.it'),
                                ])->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make('Rubrica Referenti')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Forms\Components\Fieldset::make('Elenco Dinamico Contatti')
                                ->schema([
                                    Forms\Components\Repeater::make('content_data.contacts_list')
                                        ->label('Contatti in Rubrica')
                                        ->addActionLabel('Aggiungi Referente / Dipartimento')
                                        ->itemLabel(fn (array $state): ?string => $state['role'] ?? null)
                                        ->schema([
                                            Forms\Components\TextInput::make('category')
                                                ->label('Categoria Raggruppamento')
                                                ->helperText('Es. SERIE A1, SDB VOLLEY YOUTH')
                                                ->required(),
                                            Forms\Components\TextInput::make('role')
                                                ->label('Ruolo o Nome Dipartimento')
                                                ->required()
                                                ->placeholder('es. DIRETTORE GENERALE'),
                                            Forms\Components\TextInput::make('name')
                                                ->label('Nome del Referente')
                                                ->placeholder('es. Francesco Paoletti (Opzionale)'),
                                            Forms\Components\TextInput::make('email')
                                                ->label('Indirizzo Email')
                                                ->email()
                                                ->placeholder('es. info@... (Opzionale)'),
                                            Forms\Components\TextInput::make('phone')
                                                ->label('Telefono')
                                                ->placeholder('es. 333 88 24 951 (Opzionale)'),
                                        ])
                                        ->columns(2)
                                        ->grid(2)
                                        ->collapsible()
                                        ->defaultItems(0)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Restituisce i campi per la pagina Iscrizione (Experience) del Summer Camp
     */
    public static function getCampEnrollmentSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.button_text')
                ->label('Testo del Pulsante')
                ->placeholder('es. Accedi al Portale Iscrizioni')
                ->required(),
            Forms\Components\TextInput::make('content_data.button_url')
                ->label('URL di Destinazione Esterna')
                ->placeholder('es. https://partner-organizzatore.it/iscrizioni')
                ->url()
                ->required(),
            Forms\Components\FileUpload::make('content_data.button_image')
                ->label('Pulsante Grafico (Immagine/Banner)')
                ->helperText('Se caricata, l\'immagine verrà usata come banner grafico cliccabile al posto del pulsante testuale standard.')
                ->image()
                ->directory('camp-enrollment')
                ->preserveFilenames(),
        ];
    }

    /**
     * Restituisce lo schema per la gestione del Magazine PDF
     */
    public static function getMagazineSchema(): array
    {
        return [
            Forms\Components\Repeater::make('content_data.magazines')
                ->label('Edizioni del Magazine (PDF)')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Titolo Edizione')
                        ->required()
                        ->placeholder('es. Numero 1 — Ottobre 2026'),
                    Forms\Components\TextInput::make('publish_date')
                        ->label('Data / Periodo di Pubblicazione')
                        ->placeholder('es. Ottobre 2026'),
                    Forms\Components\FileUpload::make('file_url')
                        ->label('File PDF del Magazine')
                        ->acceptedFileTypes(['application/pdf'])
                        ->directory('magazines/pdfs')
                        ->required()
                        ->preserveFilenames(),
                    Forms\Components\FileUpload::make('cover_image_url')
                        ->label('Immagine di Copertina')
                        ->image()
                        ->directory('magazines/covers')
                        ->preserveFilenames(),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->createItemButtonLabel('Aggiungi Edizione Magazine')
                ->collapsible(),
        ];
    }

    /**
     * Restituisce lo schema per la gestione dei video di Double Face
     */
    public static function getDoubleFaceSchema(): array
    {
        return [
            Forms\Components\Repeater::make('content_data.youtube_videos')
                ->label('Video YouTube (Double Face)')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Titolo Video')
                        ->required()
                        ->placeholder('es. Intervista a Ekaterina Antropova'),
                    Forms\Components\TextInput::make('youtube_url')
                        ->label('URL / Link Video YouTube')
                        ->required()
                        ->placeholder('es. https://www.youtube.com/watch?v=... o https://youtu.be/...'),
                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione (Opzionale)')
                        ->rows(2),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->createItemButtonLabel('Aggiungi Video YouTube')
                ->collapsible(),
        ];
    }

    /**
     * Restituisce il campo JSON generico per le altre pagine
     */
    public static function getGenericJsonSchema(): array
    {
        return [
            Forms\Components\KeyValue::make('content_data')
                ->label('Variabili Template (Chiave-Valore)')
                ->keyLabel('Chiave (es. hero_title)')
                ->valueLabel('Valore testuale'),
        ];
    }
}
