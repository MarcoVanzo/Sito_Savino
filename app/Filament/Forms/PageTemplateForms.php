<?php

namespace App\Filament\Forms;

use App\Filament\Forms\Templates\ComunicazioneTemplateForm;
use App\Filament\Forms\Templates\TicketingTemplateForm;
use App\Filament\Forms\Templates\YouthTemplateForm;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class PageTemplateForms
{
    /**
     * Coda multimediale di una pagina: un video e una galleria, dopo il testo.
     *
     * Le pagine dei progetti sociali raccontavano solo a parole quello che di
     * suo e' fatto di campo e di persone. Il video accetta gli stessi indirizzi
     * della diretta delle gare (App\Support\LiveStream): YouTube, Vimeo, Twitch
     * e Dailymotion, e nessun altro dominio.
     *
     * @return array<int, Forms\Components\Component>
     */
    private static function mediaTailSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.video_url')
                ->label('Video (YouTube, Vimeo, Twitch o Dailymotion)')
                ->url()
                ->helperText('Incolla il link del video. Compare in fondo alla pagina, dopo il testo.')
                ->placeholder('es. https://www.youtube.com/watch?v=...')
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('gallery')
                ->label('Galleria fotografica')
                ->helperText('Le foto compaiono in fondo alla pagina. Si riordinano trascinandole.')
                ->collection('gallery')
                ->multiple()
                ->reorderable()
                ->appendFiles()
                ->image()
                ->maxSize(4096)
                ->columnSpanFull(),
        ];
    }

    /**
     * Elenco di documenti scaricabili (PDF) di una pagina.
     *
     * Serve al bilancio di sostenibilita', che ha un'edizione per stagione, ma
     * vale per qualunque pagina di contenuto che debba allegare dei documenti.
     */
    private static function documentsRepeater(string $label): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('content_data.documents')
            ->label($label)
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titolo')
                    ->required()
                    ->placeholder('es. Bilancio di Sostenibilita\' 2024/2025'),
                Forms\Components\Textarea::make('description')
                    ->label(EtichetteDeiCampi::SHORT_DESCRIPTION)
                    ->rows(2),
                Forms\Components\FileUpload::make('file')
                    ->label('File PDF')
                    ->acceptedFileTypes([EtichetteDeiCampi::PDF_MIME])
                    ->directory('documenti')
                    ->required()
                    ->preserveFilenames()
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->columnSpanFull()
            ->defaultItems(0)
            ->createItemButtonLabel('Aggiungi documento')
            ->collapsible();
    }

    /**
     * Campi delle pagine di contenuto semplice (template "Pagina Contenuto"):
     * pulsante di richiamo e galleria fotografica, entrambi opzionali.
     *
     * La galleria usa la media library e non un upload su disco, così le
     * immagini finiscono dove finiscono tutte le altre (Spaces in produzione)
     * e gli URL restano validi anche fuori dal server web.
     */
    public static function getContentPageSchema(): array
    {
        return [
            Forms\Components\Fieldset::make('Pulsante')
                ->schema([
                    Forms\Components\TextInput::make('content_data.button_text')
                        ->label(EtichetteDeiCampi::BUTTON_TEXT)
                        ->placeholder('es. Scrivici'),
                    Forms\Components\TextInput::make('content_data.button_url')
                        ->label('Destinazione')
                        ->helperText('Indirizzo web oppure mailto:indirizzo@dominio.it')
                        ->placeholder('es. mailto:marketing@savinodelbenevolley.it'),
                ])
                ->columns(2),
            self::documentsRepeater('Documenti scaricabili'),
            ...self::mediaTailSchema(),
        ];
    }

    /**
     * Campi del template "Talent Day".
     *
     * La pagina prendeva in prestito il modello del Summer Camp e mostrava i
     * contenuti di quello: le tappe di selezione, i turni per anno di nascita e
     * il modulo d'iscrizione non avevano dove stare.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function getTalentDaySchema(): array
    {
        return [
            Forms\Components\Fieldset::make('Intestazione')
                ->schema([
                    Forms\Components\TextInput::make('content_data.hero_label')
                        ->label('Etichetta sopra il titolo')
                        ->placeholder('es. Talent Scouting'),
                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                        ->label('Sottotitolo')
                        ->placeholder('es. Insegui il Tuo Sogno'),
                ])
                ->columns(2),

            Forms\Components\TextInput::make('content_data.stages_title')
                ->label('Titolo della sezione tappe')
                ->placeholder('es. Le Tappe'),
            Forms\Components\Repeater::make('content_data.stages')
                ->label('Tappe')
                ->schema([
                    Forms\Components\TextInput::make('date')
                        ->label('Data')
                        ->required()
                        ->placeholder('es. 19 Maggio'),
                    Forms\Components\TextInput::make('place')
                        ->label('Sede')
                        ->placeholder('es. Palasport, via Rialdoli, Scandicci (FI)'),
                    Forms\Components\TextInput::make('status')
                        ->label('Stato')
                        ->placeholder('es. Disponibile oppure Sold out'),
                    Forms\Components\Toggle::make('sold_out')
                        ->label('Esaurita')
                        ->helperText('Spegne l\'evidenza colorata sullo stato.'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0)
                ->createItemButtonLabel('Aggiungi tappa')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => trim(($state['date'] ?? '').' — '.($state['place'] ?? '')) ?: null),

            Forms\Components\TextInput::make('content_data.slots_title')
                ->label('Titolo della sezione turni')
                ->placeholder('es. Orari e Categorie'),
            Forms\Components\Repeater::make('content_data.slots')
                ->label('Turni')
                ->schema([
                    Forms\Components\TextInput::make('time')
                        ->label('Orario')
                        ->placeholder('es. 16:00-17:30'),
                    Forms\Components\TextInput::make('years')
                        ->label('Anni di nascita')
                        ->placeholder('es. atlete nate dal 2012 al 2014'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0)
                ->createItemButtonLabel('Aggiungi turno'),

            Forms\Components\Fieldset::make('Iscrizione')
                ->schema([
                    Forms\Components\TextInput::make('content_data.signup_title')
                        ->label('Titolo'),
                    Forms\Components\Textarea::make('content_data.signup_description')
                        ->label('Descrizione')
                        ->rows(2),
                    Forms\Components\TextInput::make('content_data.signup_url')
                        ->label('Link al modulo d\'iscrizione')
                        ->url()
                        ->placeholder('https://...'),
                    Forms\Components\TextInput::make('content_data.signup_cta')
                        ->label(EtichetteDeiCampi::BUTTON_TEXT)
                        ->placeholder('es. Iscriviti'),
                    Forms\Components\TextInput::make('content_data.signup_email')
                        ->label('Email per informazioni')
                        ->email()
                        ->helperText('Se vuoto vale quella in Impostazioni -> Contatti.'),
                    Forms\Components\Textarea::make('content_data.partners')
                        ->label('Societa\' partner')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            ...self::mediaTailSchema(),
        ];
    }

    /**
     * Restituisce i campi specifici per il template "Società"
     */
    public static function getSocietaSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.hero_subheading')
                ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                ->placeholder('es. Dal 1982'),
            Forms\Components\Textarea::make('content_data.hero_description')
                ->label(EtichetteDeiCampi::HERO_DESCRIPTION)
                ->helperText('Mostrata sotto al titolo quando la pagina non ha un contenuto testuale.')
                ->rows(3),
        ];
    }

    /**
     * Biglietteria e Campagna Abbonamenti: il form sta in TicketingTemplateForm.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function getTicketingSchema(): array
    {
        return TicketingTemplateForm::schema();
    }

    /**
     * Convenzioni per gli abbonati: i partner che offrono agevolazioni, con
     * il link al loro sito e il riassunto dello sconto. L'introduzione sta
     * nell'editor della pagina.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function getConvenzioniSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.hero_label')
                ->label(EtichetteDeiCampi::HERO_BADGE),
            Forms\Components\Textarea::make('content_data.hero_subtitle')
                ->label(EtichetteDeiCampi::HERO_SUBTITLE),
            Forms\Components\TextInput::make('content_data.partners_heading')
                ->label('Titolo dell\'elenco dei partner')
                ->placeholder('es. I nostri partner'),
            Forms\Components\Textarea::make('content_data.partners_empty')
                ->label('Testo quando non ci sono partner')
                ->rows(2)
                ->placeholder('es. Le convenzioni della stagione sono in arrivo.'),
            Forms\Components\Repeater::make('content_data.partners')
                ->label('Partner convenzionati')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome del partner')
                        ->required()
                        ->placeholder('es. Trattoria da Mario'),
                    Forms\Components\TextInput::make('url')
                        ->label('Sito o pagina dedicata')
                        ->url()
                        ->placeholder('es. https://www.partner.it/savino')
                        ->helperText('Il nome del partner rimanda qui.'),
                    Forms\Components\TextInput::make('discount')
                        ->label('Sconto')
                        ->placeholder('es. 10%'),
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo (facoltativo)')
                        ->image()
                        ->directory('convenzioni')
                        ->maxSize(2048),
                    Forms\Components\Textarea::make('description')
                        ->label('Su cosa si applica')
                        ->rows(2)
                        ->placeholder('es. Sul conto a pranzo e a cena, bevande escluse')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('how_to_use')
                        ->label('Come usarla')
                        ->rows(2)
                        ->placeholder('es. Presentando la tessera dell\'abbonamento e un documento d\'identità')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->createItemButtonLabel('Aggiungi partner'),
        ];
    }

    /**
     * SDB Volley Club Race: regolamento nell'editor della pagina, classifica
     * delle societa' compilata a mano. L'ordine in pagina lo decide il
     * punteggio, non la posizione nell'elenco.
     */
    public static function getClubRaceSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.hero_label')
                ->label(EtichetteDeiCampi::HERO_BADGE),
            Forms\Components\Textarea::make('content_data.hero_subtitle')
                ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                ->rows(2),
            Forms\Components\Fieldset::make('Classifica')
                ->schema([
                    Forms\Components\TextInput::make('content_data.standings_title')
                        ->label('Titolo della classifica')
                        ->placeholder('es. Classifica'),
                    Forms\Components\TextInput::make('content_data.standings_updated')
                        ->label('Aggiornata al')
                        ->placeholder('es. 12 gennaio 2027'),
                    Forms\Components\Repeater::make('content_data.standings')
                        ->label('Società in classifica')
                        ->schema([
                            Forms\Components\TextInput::make('club')
                                ->label('Società')
                                ->required(),
                            Forms\Components\TextInput::make('points')
                                ->label('Punti')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->createItemButtonLabel('Aggiungi società')
                        ->itemLabel(fn (array $state): ?string => $state['club'] ?? null)
                        ->collapsible(),
                    Forms\Components\Textarea::make('content_data.standings_note')
                        ->label('Nota sotto la classifica')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),
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
                ->placeholder('es. Pala BigMat'),
            Forms\Components\TextInput::make('content_data.venue_address')
                ->label('Indirizzo completo')
                ->placeholder('es. Via del Tridente, 5 — 50127 Firenze (FI)'),
            Forms\Components\TextInput::make('content_data.maps_link')
                ->label('Link Google Maps (Pulsante "Apri su Maps")')
                ->placeholder('es. https://maps.app.goo.gl/...'),
            // Google dà il codice HTML intero (Condividi → Incorpora una mappa):
            // chi lo incolla così com'è non deve sapere cosa sia un `src`.
            Forms\Components\TextInput::make('content_data.maps_iframe_src')
                ->label('Mappa Google incorporata')
                ->placeholder('es. <iframe src="https://www.google.com/maps/embed?pb=…"></iframe>')
                ->helperText('Su Google Maps cerca il palazzetto, poi Condividi → Incorpora una mappa → Copia HTML e incolla qui tutto il codice. Se lasci vuoto, la mappa si centra sull\'indirizzo qui sopra.')
                ->dehydrateStateUsing(fn (?string $state): ?string => self::srcDellaMappa($state)),
            Forms\Components\Repeater::make('content_data.services')
                ->label('Servizi della struttura')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome Servizio')
                        ->required()
                        ->placeholder('es. Capienza 3500 Posti'),
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
     * L'indirizzo della mappa da quello che la redazione incolla: il codice
     * `<iframe …>` di Google oppure il solo indirizzo.
     */
    public static function srcDellaMappa(?string $valore): ?string
    {
        $valore = trim((string) $valore);

        if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $valore, $trovato)) {
            $valore = $trovato[1];
        }

        $valore = html_entity_decode($valore, ENT_QUOTES | ENT_HTML5);

        return $valore === '' ? null : $valore;
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
                        ->label(EtichetteDeiCampi::SHORT_DESCRIPTION)
                        ->placeholder('es. Linee guida specifiche per garantire un ambiente sportivo sicuro...'),
                    // Era un campo di testo che chiedeva l'indirizzo del file: per usarlo
                    // bisognava aver gia' caricato il PDF da qualche altra parte e
                    // conoscerne il percorso, cosa che dal pannello non si puo' fare.
                    // Non obbligatorio: i documenti del seeder hanno il segnaposto "#",
                    // che il form scarta, e un solo documento senza PDF bloccava il
                    // salvataggio di tutti gli altri. Senza file il sito non mostra
                    // il pulsante di download.
                    Forms\Components\FileUpload::make('file')
                        ->label('File PDF del documento')
                        ->acceptedFileTypes([EtichetteDeiCampi::PDF_MIME])
                        ->directory('safeguarding')
                        ->helperText('Senza PDF il documento compare sul sito senza pulsante di download.')
                        ->preserveFilenames(),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icona SVG (Opzionale)')
                        ->placeholder('es. M12 4.354a4...'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0)
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
                                    Forms\Components\TextInput::make('content_data.form_subtitle')
                                        ->label('Sopratitolo del Modulo')
                                        ->placeholder('es. Scrivici direttamente'),
                                    Forms\Components\TextInput::make('content_data.form_title')
                                        ->label('Titolo del Modulo')
                                        ->placeholder('es. Scrivici'),
                                    Forms\Components\TextInput::make('content_data.form_success_message')
                                        ->label('Messaggio di Conferma Invio')
                                        ->placeholder('es. Messaggio inviato con successo!'),
                                ])->columns(2),

                            // Il template legge queste etichette da content_data:
                            // senza i campi qui restavano scritte online che in
                            // redazione non si trovavano da nessuna parte.
                            Forms\Components\Fieldset::make('Etichette del modulo')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.form_label_name')
                                        ->label('Etichetta "Nome"')
                                        ->placeholder('es. Nome e Cognome'),
                                    Forms\Components\TextInput::make('content_data.form_placeholder_name')
                                        ->label('Testo guida "Nome"')
                                        ->placeholder('es. Mario Rossi'),
                                    Forms\Components\TextInput::make('content_data.form_label_email')
                                        ->label('Etichetta "Email"'),
                                    Forms\Components\TextInput::make('content_data.form_placeholder_email')
                                        ->label('Testo guida "Email"')
                                        ->placeholder('es. mario.rossi@email.it'),
                                    Forms\Components\TextInput::make('content_data.form_label_subject')
                                        ->label('Etichetta "Oggetto"'),
                                    Forms\Components\TextInput::make('content_data.form_label_message')
                                        ->label('Etichetta "Messaggio"'),
                                    Forms\Components\TextInput::make('content_data.form_placeholder_message')
                                        ->label('Testo guida "Messaggio"')
                                        ->placeholder('es. Scrivi il tuo messaggio...'),
                                    Forms\Components\TextInput::make('content_data.form_submit_label')
                                        ->label('Pulsante di invio')
                                        ->placeholder('es. Invia Messaggio'),
                                    Forms\Components\TextInput::make('content_data.form_sending_label')
                                        ->label('Pulsante durante l\'invio')
                                        ->placeholder('es. Invio in corso...'),
                                    Forms\Components\TextInput::make('content_data.form_reset_label')
                                        ->label('Pulsante di annullamento')
                                        ->placeholder('es. Annulla'),
                                ])->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make('Argomenti del Modulo')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->schema([
                            Forms\Components\Repeater::make('content_data.form_topics')
                                ->label('Argomenti selezionabili')
                                ->helperText('Compaiono nella tendina "Oggetto" del modulo di contatto. Il suggerimento, se compilato, appare quando il visitatore sceglie quell\'argomento.')
                                ->addActionLabel('Aggiungi argomento')
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['value'] ?? null)
                                ->schema([
                                    Forms\Components\TextInput::make('value')
                                        ->label('Argomento')
                                        ->helperText('Testo che arriva nella mail.')
                                        ->required(),
                                    Forms\Components\TextInput::make('label')
                                        ->label('Etichetta mostrata')
                                        ->helperText('Se vuota si usa l\'argomento.'),
                                    Forms\Components\TextInput::make('tip_title')
                                        ->label('Titolo del suggerimento'),
                                    Forms\Components\Textarea::make('tip_text')
                                        ->label('Testo del suggerimento')
                                        ->rows(2),
                                    Forms\Components\TextInput::make('tip_link_text')
                                        ->label('Testo del link'),
                                    Forms\Components\TextInput::make('tip_link_url')
                                        ->label('Indirizzo del link')
                                        ->placeholder('es. /ticketing'),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Recapiti e Dati Societari')
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Forms\Components\Placeholder::make('recapiti_spostati')
                                ->label('Recapiti, sede e dati fiscali')
                                ->content('Si modificano in Impostazioni → Contatti: da lì valgono per tutto il sito (footer compreso). I campi che stavano qui salvavano una copia che le pagine non leggevano.'),
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
                        ->acceptedFileTypes([EtichetteDeiCampi::PDF_MIME])
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
     * Il modello della pagina non ha campi propri.
     *
     * Qui c'era un `KeyValue::make('content_data')`, nascosto per i modelli
     * con un form dedicato. Un campo nascosto non viene deidratato, e Filament
     * a quel punto toglie dallo stato tutto ciò che sta sotto il suo percorso:
     * `content_data` intero, compresi i campi `content_data.*` dei modelli
     * veri. Per questo salvare una pagina la svuotava, e il salvataggio era
     * stato dirottato sullo stato grezzo di Livewire (vedi PreservaContentData).
     * Nessuna pagina in archivio usa piu' la mappa chiave-valore: i modelli
     * senza form (Stagione, Roster, Shop...) non leggono `content_data`.
     */
    public static function getGenericJsonSchema(): array
    {
        return [
            Forms\Components\Placeholder::make('content_data_assente')
                ->label('Variabili Template')
                ->content('Questo modello non ha campi propri: il contenuto si scrive nell\'editor qui sopra.'),
        ];
    }

    /**
     * Schema del form per il Summer Camp
     */
    public static function getSummerCampSchema(): array
    {
        return [
            Forms\Components\Tabs::make('Summer Camp')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Informazioni Generali')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Fieldset::make('Intestazioni e Copertina')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_label')
                                        ->label(EtichetteDeiCampi::HERO_BADGE)
                                        ->placeholder('es. SUMMER CAMP & EXPERIENCE'),
                                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                                        ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                                        ->placeholder('es. Entra nel Mondo del Volley Professionistico'),
                                    Forms\Components\TextInput::make('content_data.camp_section_label')
                                        ->label('Etichetta Sezione Camp')
                                        ->placeholder('es. IL CAMP'),
                                    Forms\Components\TextInput::make('content_data.camp_title')
                                        ->label('Titolo Sezione Camp')
                                        ->placeholder('es. Un\'Esperienza Unica'),
                                ]),
                            Forms\Components\Fieldset::make('Descrizione del Camp')
                                ->schema([
                                    Forms\Components\Textarea::make('content_data.camp_description_1')
                                        ->label('Descrizione Paragrafo 1')
                                        ->rows(3),
                                    Forms\Components\Textarea::make('content_data.camp_description_2')
                                        ->label('Descrizione Paragrafo 2')
                                        ->rows(3),
                                ])->columns(1),
                            Forms\Components\Fieldset::make('Badge Camp (Volley Experience)')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.camp_badge_title')
                                        ->label('Titolo Badge')
                                        ->placeholder('es. Summer Camp 2026'),
                                    Forms\Components\TextInput::make('content_data.camp_badge_subtitle')
                                        ->label('Sottotitolo Badge')
                                        ->placeholder('es. Scandicci & Firenze'),
                                ])->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make('Attività')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.activities_section_label')
                                ->label('Etichetta Sezione Attività')
                                ->placeholder('es. LE NOSTRE ATTIVITÀ'),
                            Forms\Components\TextInput::make('content_data.activities_title')
                                ->label('Titolo Sezione Attività')
                                ->placeholder('es. Non Solo Pallavolo'),
                            Forms\Components\Repeater::make('content_data.activities')
                                ->label('Elenco Attività')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Titolo Attività')
                                        ->required(),
                                    Forms\Components\TextInput::make('icon')
                                        ->label('Emoji / Icona')
                                        ->placeholder('es. 🏐')
                                        ->required(),
                                    Forms\Components\Textarea::make('description')
                                        ->label('Descrizione Attività')
                                        ->required()
                                        ->rows(2),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->createItemButtonLabel('Aggiungi Attività')
                                ->collapsible(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Date e Turni')
                        ->icon('heroicon-o-calendar')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.dates_section_label')
                                ->label('Etichetta Sezione Date')
                                ->placeholder('es. TURNI & DATE'),
                            Forms\Components\TextInput::make('content_data.dates_title')
                                ->label('Titolo Sezione Date')
                                ->placeholder('es. Scegli la Tua Settimana'),
                            Forms\Components\Repeater::make('content_data.dates')
                                ->label('Elenco Turni')
                                ->schema([
                                    Forms\Components\TextInput::make('period')
                                        ->label('Periodo (es. Settimana 1)')
                                        ->required(),
                                    Forms\Components\TextInput::make('dates')
                                        ->label('Date Effettive (es. 15 - 20 Giugno)')
                                        ->required(),
                                    Forms\Components\Select::make('status')
                                        ->label('Stato Turno')
                                        ->options([
                                            'Iscrizioni Aperte' => 'Iscrizioni Aperte',
                                            'Ultimi Posti' => 'Ultimi Posti',
                                            'Sold Out' => 'Sold Out',
                                            'In Arrivo' => 'In Arrivo',
                                        ])
                                        ->required(),
                                ])
                                ->columns(3)
                                ->columnSpanFull()
                                ->createItemButtonLabel('Aggiungi Turno')
                                ->collapsible(),
                        ]),

                    Forms\Components\Tabs\Tab::make('In Evidenza')
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Forms\Components\TagsInput::make('content_data.highlights')
                                ->label('Punti di Forza (Highlights)')
                                ->placeholder('Scrivi e premi Invio per aggiungere (es. Staff Tecnico Qualificato)'),
                            Forms\Components\Fieldset::make('Call to Action (Iscrizione)')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.cta_text')
                                        ->label('Testo Pulsante CTA')
                                        ->placeholder('es. Iscriviti Ora'),
                                    Forms\Components\TextInput::make('content_data.cta_url')
                                        ->label('URL Pulsante CTA (Se vuoto, usa email di contatto)')
                                        ->placeholder('es. https://...'),
                                ])->columns(1),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Schema del form per i Progetti Sociali
     */
    public static function getSocialeSchema(): array
    {
        return [
            Forms\Components\Tabs::make('Progetti Sociali')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Informazioni Generali')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Fieldset::make('Intestazioni e Mission')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_badge')
                                        ->label(EtichetteDeiCampi::HERO_BADGE)
                                        ->placeholder('es. PROGETTI SOCIALI'),
                                    Forms\Components\TextInput::make('content_data.hero_description')
                                        ->label(EtichetteDeiCampi::HERO_DESCRIPTION)
                                        ->placeholder('es. Più di uno sport: un impegno costante verso il territorio...'),
                                    Forms\Components\TextInput::make('content_data.mission_badge')
                                        ->label('Etichetta Sezione Missione')
                                        ->placeholder('es. LA NOSTRA MISSIONE'),
                                    Forms\Components\TextInput::make('content_data.mission_title')
                                        ->label('Titolo Missione')
                                        ->placeholder('es. I Nostri Valori in Campo'),
                                ]),
                            Forms\Components\Fieldset::make('Titoli Sezioni')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.initiatives_badge')
                                        ->label('Etichetta Sezione Iniziative')
                                        ->placeholder('es. COSA FACCIAMO'),
                                    Forms\Components\TextInput::make('content_data.initiatives_title')
                                        ->label('Titolo Sezione Iniziative')
                                        ->placeholder('es. Le Nostre Iniziative'),
                                    Forms\Components\TextInput::make('content_data.results_badge')
                                        ->label('Etichetta Sezione Impatto')
                                        ->placeholder('es. I RISULTATI'),
                                    Forms\Components\TextInput::make('content_data.impact_title')
                                        ->label('Titolo Sezione Impatto')
                                        ->placeholder('es. Il Nostro Impatto'),
                                ]),
                            Forms\Components\Fieldset::make('Testi della Missione')
                                ->schema([
                                    Forms\Components\Textarea::make('content_data.mission_text_1')
                                        ->label('Testo Paragrafo 1')
                                        ->rows(3),
                                    Forms\Components\Textarea::make('content_data.mission_text_2')
                                        ->label('Testo Paragrafo 2')
                                        ->rows(3),
                                ])->columns(1),
                        ]),

                    Forms\Components\Tabs\Tab::make('Progetti')
                        ->icon('heroicon-o-folder-open')
                        ->schema([
                            Forms\Components\Repeater::make('content_data.projects')
                                ->label('Elenco Progetti')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Titolo Progetto')
                                        ->required(),
                                    Forms\Components\TextInput::make('tag')
                                        ->label('Tag / Categoria')
                                        ->placeholder('es. INCLUSIONE'),
                                    Forms\Components\TextInput::make('icon')
                                        ->label('Emoji / Icona')
                                        ->placeholder('es. 🏐'),
                                    Forms\Components\Select::make('color')
                                        ->label('Colore Tema')
                                        ->options([
                                            'savino-blue' => 'Blu Savino',
                                            'savino-red' => 'Rosso Savino',
                                            'savino-pink' => 'Rosa Savino',
                                            'savino-fucsia' => 'Fucsia Savino',
                                        ])
                                        ->required(),
                                    Forms\Components\Textarea::make('description')
                                        ->label('Descrizione Progetto')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('link')
                                        ->label('Link "Scopri" (opzionale)')
                                        ->placeholder('es. /sociale/volley-4-all oppure https://...')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->createItemButtonLabel('Aggiungi Progetto')
                                ->collapsible(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Impatto e Numeri')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Forms\Components\Repeater::make('content_data.impact_stats')
                                ->label('Statistiche d\'Impatto')
                                ->schema([
                                    Forms\Components\TextInput::make('value')
                                        ->label('Valore (es. 500+ o €50K)')
                                        ->required(),
                                    Forms\Components\TextInput::make('label')
                                        ->label('Etichetta / Descrizione (es. Ragazzi Coinvolti)')
                                        ->required(),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->createItemButtonLabel('Aggiungi Statistica')
                                ->collapsible(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Foto e Video')
                        ->icon('heroicon-o-photo')
                        ->schema(self::mediaTailSchema()),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Schema del form per la pagina Sponsor
     */
    public static function getSponsorSchema(): array
    {
        return [
            Forms\Components\Tabs::make('Sponsor')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Testi Hero')
                        ->icon('heroicon-o-presentation-chart-bar')
                        ->schema([
                            Forms\Components\TextInput::make('content_data.hero_subtitle')
                                ->label(EtichetteDeiCampi::HERO_SUBTITLE)
                                ->placeholder('es. I NOSTRI PARTNER'),
                            Forms\Components\Textarea::make('content_data.hero_description')
                                ->label(EtichetteDeiCampi::HERO_DESCRIPTION)
                                ->placeholder('es. Grazie al sostegno dei nostri sponsor continuiamo a crescere...'),
                        ]),

                    Forms\Components\Tabs\Tab::make('Call to Action & Statistiche')
                        ->icon('heroicon-o-hand-raised')
                        ->schema([
                            Forms\Components\Fieldset::make('Testi Call to Action ("Diventa Partner")')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.cta_subtitle')
                                        ->label('Sottotitolo CTA')
                                        ->placeholder('es. UNISCITI A NOI'),
                                    Forms\Components\TextInput::make('content_data.cta_title')
                                        ->label('Titolo CTA')
                                        ->placeholder('es. Diventa Partner'),
                                    Forms\Components\Textarea::make('content_data.cta_description')
                                        ->label('Descrizione CTA')
                                        ->placeholder('es. Unisciti alla famiglia della Savino Del Bene Volley...'),
                                    Forms\Components\TextInput::make('content_data.cta_button_text')
                                        ->label('Testo Pulsante CTA')
                                        ->placeholder('es. Diventa Partner'),
                                    Forms\Components\TextInput::make('content_data.contact_email')
                                        ->label('Email che riceve le richieste')
                                        ->email()
                                        ->helperText('Se vuota si usa l\'email di contatto generale del sito.'),
                                    Forms\Components\TextInput::make('content_data.contact_subject')
                                        ->label('Oggetto della mail')
                                        ->placeholder('es. Savino Del Bene Volley — Richiesta di sponsorizzazione')
                                        ->helperText('Viene precompilato nella mail di chi scrive.'),
                                ]),

                            Forms\Components\Fieldset::make('Statistiche d\'Impatto')
                                ->schema([
                                    CampiDelleStatistiche::griglia([
                                        ['valore' => 'content_data.stat1_value', 'etichetta' => 'content_data.stat1_label',
                                            'nome' => 'Stat 1', 'esempioValore' => 'es. 2M+', 'esempioEtichetta' => 'es. Social Reach'],
                                        ['valore' => 'content_data.stat2_value', 'etichetta' => 'content_data.stat2_label',
                                            'nome' => 'Stat 2', 'esempioValore' => 'es. 50K+', 'esempioEtichetta' => 'es. Spettatori'],
                                        ['valore' => 'content_data.stat3_value', 'etichetta' => 'content_data.stat3_label',
                                            'nome' => 'Stat 3', 'esempioValore' => 'es. 100+', 'esempioEtichetta' => 'es. Eventi Societari'],
                                    ], 3),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Schema del form per il Settore Giovanile
     */
    public static function getYouthSchema(): array
    {
        return [
            Forms\Components\Tabs::make('Settore Giovanile')
                ->tabs([
                    ...YouthTemplateForm::schedaInfoEStatistiche(),
                    ...YouthTemplateForm::schedaScouting(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Schema del form per la pagina Comunicazione
     */
    public static function getComunicazioneSchema(): array
    {
        return [
            Forms\Components\Tabs::make('Comunicazione')
                ->tabs([
                    ...ComunicazioneTemplateForm::schedaAccrediti(),
                    ...ComunicazioneTemplateForm::schedaCartelleStampa(),
                    ...ComunicazioneTemplateForm::schedaContattiMedia(),
                ])
                ->columnSpanFull(),
        ];
    }
}
