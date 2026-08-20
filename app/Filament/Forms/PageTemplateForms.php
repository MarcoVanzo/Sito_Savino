<?php

namespace App\Filament\Forms;

use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;

class PageTemplateForms
{
    /**
     * Campo di upload (PDF/ZIP) per un Press Kit della pagina Comunicazione.
     */
    private static function pressKitFileField(int $n): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make("content_data.press_kit_{$n}_file")
            ->label('File da scaricare (PDF/ZIP)')
            ->acceptedFileTypes(['application/pdf', 'application/zip', 'application/x-zip-compressed'])
            ->directory('press-kit')
            ->preserveFilenames();
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
                        ->label('Testo del pulsante')
                        ->placeholder('es. Scrivici'),
                    Forms\Components\TextInput::make('content_data.button_url')
                        ->label('Destinazione')
                        ->helperText('Indirizzo web oppure mailto:indirizzo@dominio.it')
                        ->placeholder('es. mailto:marketing@savinodelbenevolley.it'),
                ])
                ->columns(2),
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
     * Restituisce i campi specifici per il template "Società"
     */
    public static function getSocietaSchema(): array
    {
        return [
            Forms\Components\TextInput::make('content_data.hero_subheading')
                ->label('Sottotitolo Hero')
                ->placeholder('es. Dal 1982'),
            Forms\Components\Textarea::make('content_data.hero_description')
                ->label('Descrizione Hero')
                ->helperText('Mostrata sotto al titolo quando la pagina non ha un contenuto testuale.')
                ->rows(3),
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
            Forms\Components\Fieldset::make('Biglietteria online (Vivaticket)')
                ->schema([
                    Forms\Components\TextInput::make('content_data.tickets_url')
                        ->label('Link alla biglietteria')
                        ->url()
                        ->placeholder('https://www.vivaticket.com/it/...')
                        ->helperText('Indirizzo della pagina di vendita. Se vuoto, il pulsante non viene mostrato.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('content_data.tickets_button_text')
                        ->label('Testo del pulsante')
                        ->placeholder('es. Acquista su Vivaticket'),
                    Forms\Components\TextInput::make('content_data.tickets_note')
                        ->label('Nota sotto al pulsante')
                        ->placeholder('es. Vendita gestita da Vivaticket'),
                ])
                ->columns(2),
            Forms\Components\TextInput::make('content_data.plans_heading')
                ->label('Titolo Sezione Abbonamenti'),
            Forms\Components\Textarea::make('content_data.plans_empty')
                ->label('Testo quando non ci sono listini pubblicati')
                ->rows(2),
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
                    Forms\Components\TextInput::make('cta_url')
                        ->label('Link Pulsante (URL acquisto/abbonamento)')
                        ->url()
                        ->placeholder('es. https://www.vivaticket.com/...'),
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
                ->placeholder('es. PalaBigmat'),
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
                ->valueLabel('Valore testuale')
                // La chiusura predefinita di KeyValue tipizza i valori come
                // ?string: con un contenuto annidato (o con lo stato per lingua
                // del plugin translatable) il salvataggio andava in errore e il
                // form della pagina non si salvava affatto.
                ->dehydrateStateUsing(fn (?array $state) => collect($state ?? [])
                    ->filter(fn ($value, $key) => filled($key))
                    ->all())
                // Le pagine con struttura annidata (hero, plans, timeline…) sono
                // gestite dallo schema del loro template: mostrarle come coppie
                // chiave-valore le appiattirebbe.
                ->visible(fn (?Model $record) => self::hasFlatContentData($record)),

            Forms\Components\Placeholder::make('content_data_structured')
                ->label('Variabili Template')
                ->content('Questa pagina usa contenuti strutturati gestiti dal proprio template: non sono modificabili come coppie chiave-valore.')
                ->visible(fn (?Model $record) => $record !== null && ! self::hasFlatContentData($record)),
        ];
    }

    /**
     * Vero se `content_data` è una mappa piatta di valori testuali, l'unica
     * forma che il campo chiave-valore sa rappresentare senza perdere dati.
     */
    private static function hasFlatContentData(?Model $record): bool
    {
        $data = $record?->getAttributes()['content_data'] ?? null;

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data) || $data === []) {
            return true;
        }

        // La colonna è translatable: si guarda dentro al livello della lingua,
        // altrimenti qualunque pagina risulterebbe "annidata".
        $locales = config('app.supported_locales', ['it', 'en']);

        if (array_diff(array_keys($data), $locales) === []) {
            $data = reset($data);

            if (! is_array($data) || $data === []) {
                return true;
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return true;
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
                                        ->label('Etichetta Hero')
                                        ->placeholder('es. SUMMER CAMP & EXPERIENCE'),
                                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                                        ->label('Sottotitolo Hero')
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
                                        ->label('Etichetta Hero')
                                        ->placeholder('es. PROGETTI SOCIALI'),
                                    Forms\Components\TextInput::make('content_data.hero_description')
                                        ->label('Descrizione Hero')
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
                                        ->required()
                                        ->placeholder('es. INCLUSIONE'),
                                    Forms\Components\TextInput::make('icon')
                                        ->label('Emoji / Icona')
                                        ->placeholder('es. 🏐')
                                        ->required(),
                                    Forms\Components\Select::make('color')
                                        ->label('Colore Tema')
                                        ->options([
                                            'savino-blue' => 'Blu Savino',
                                            'savino-red' => 'Rosso Savino',
                                            'savino-pink' => 'Rosa Savino',
                                            'savino-gold' => 'Oro Savino',
                                        ])
                                        ->required(),
                                    Forms\Components\Textarea::make('description')
                                        ->label('Descrizione Progetto')
                                        ->required()
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
                                ->label('Sottotitolo Hero')
                                ->placeholder('es. I NOSTRI PARTNER'),
                            Forms\Components\Textarea::make('content_data.hero_description')
                                ->label('Descrizione Hero')
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
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\Group::make([
                                                Forms\Components\TextInput::make('content_data.stat1_value')
                                                    ->label('Valore Stat 1')
                                                    ->placeholder('es. 2M+'),
                                                Forms\Components\TextInput::make('content_data.stat1_label')
                                                    ->label('Etichetta Stat 1')
                                                    ->placeholder('es. Social Reach'),
                                            ]),
                                            Forms\Components\Group::make([
                                                Forms\Components\TextInput::make('content_data.stat2_value')
                                                    ->label('Valore Stat 2')
                                                    ->placeholder('es. 50K+'),
                                                Forms\Components\TextInput::make('content_data.stat2_label')
                                                    ->label('Etichetta Stat 2')
                                                    ->placeholder('es. Spettatori'),
                                            ]),
                                            Forms\Components\Group::make([
                                                Forms\Components\TextInput::make('content_data.stat3_value')
                                                    ->label('Valore Stat 3')
                                                    ->placeholder('es. 100+'),
                                                Forms\Components\TextInput::make('content_data.stat3_label')
                                                    ->label('Etichetta Stat 3')
                                                    ->placeholder('es. Eventi Societari'),
                                            ]),
                                        ]),
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
                    Forms\Components\Tabs\Tab::make('Info & Statistiche')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Fieldset::make('Hero')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                                        ->label('Sottotitolo Hero')
                                        ->placeholder('es. LINEA VERDE'),
                                    Forms\Components\Textarea::make('content_data.hero_description')
                                        ->label('Descrizione Hero')
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
                                            'savino-gold' => 'Oro Savino',
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
                                ->label('Testo Pulsante Primario')
                                ->placeholder('es. Scrivici'),
                            Forms\Components\TextInput::make('content_data.scouting_email')
                                ->label('Email Scouting')
                                ->placeholder('es. giovanili@savinodelbenevolley.it'),
                            Forms\Components\TextInput::make('content_data.scouting_cta_secondary')
                                ->label('Testo Pulsante Secondario')
                                ->placeholder('es. Regolamento Prove'),
                        ]),
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
                    Forms\Components\Tabs\Tab::make('Hero & Accrediti')
                        ->icon('heroicon-o-newspaper')
                        ->schema([
                            Forms\Components\Fieldset::make('Hero')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_badge')
                                        ->label('Etichetta Hero')
                                        ->placeholder('es. AREA COMUNICAZIONE'),
                                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                                        ->label('Sottotitolo Hero')
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
                                    Forms\Components\TextInput::make('content_data.accreditation_email')
                                        ->label('Email Invio Richieste Accredito')
                                        ->placeholder('es. stampa@savinodelbenevolley.it'),
                                ])->columns(1),

                            Forms\Components\Fieldset::make('Fasi della Procedura')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.accreditation_steps_title')
                                        ->label('Titolo Fasi Procedura')
                                        ->placeholder('es. Come Richiedere l\'Accredito'),
                                    Forms\Components\Textarea::make('content_data.accreditation_step_1')
                                        ->label('Fase 1: Invio Domanda')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('content_data.accreditation_step_2')
                                        ->label('Fase 2: Valutazione')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('content_data.accreditation_step_3')
                                        ->label('Fase 3: Conferma')
                                        ->rows(2),
                                ])->columns(1),

                            Forms\Components\Fieldset::make('Area Media Hub')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.media_hub_title')
                                        ->label('Titolo Media Hub')
                                        ->placeholder('es. Media Hub'),
                                    Forms\Components\TextInput::make('content_data.media_hub_subtitle')
                                        ->label('Sottotitolo Media Hub')
                                        ->placeholder('es. Accedi alla galleria multimediale ufficiale'),
                                ]),
                        ]),

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

                            Forms\Components\Fieldset::make('Press Kit 1 (Foto SDB Volley)')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('content_data.press_kit_1_icon')
                                                ->label('Icona/Emoji')
                                                ->placeholder('es. 📸'),
                                            Forms\Components\TextInput::make('content_data.press_kit_1_title')
                                                ->label('Titolo')
                                                ->placeholder('es. Foto Ufficiali'),
                                            Forms\Components\TextInput::make('content_data.press_kit_1_format')
                                                ->label('Formato/Peso')
                                                ->placeholder('es. ZIP — 45 MB'),
                                        ]),
                                    Forms\Components\Textarea::make('content_data.press_kit_1_description')
                                        ->label('Descrizione Press Kit 1')
                                        ->rows(2),
                                    self::pressKitFileField(1),
                                ])->columns(1),

                            Forms\Components\Fieldset::make('Press Kit 2 (Loghi e Brand Assets)')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('content_data.press_kit_2_icon')
                                                ->label('Icona/Emoji')
                                                ->placeholder('es. 🎨'),
                                            Forms\Components\TextInput::make('content_data.press_kit_2_title')
                                                ->label('Titolo')
                                                ->placeholder('es. Loghi e Brand Assets'),
                                            Forms\Components\TextInput::make('content_data.press_kit_2_format')
                                                ->label('Formato/Peso')
                                                ->placeholder('es. ZIP — 12 MB'),
                                        ]),
                                    Forms\Components\Textarea::make('content_data.press_kit_2_description')
                                        ->label('Descrizione Press Kit 2')
                                        ->rows(2),
                                    self::pressKitFileField(2),
                                ])->columns(1),

                            Forms\Components\Fieldset::make('Press Kit 3 (Cartella Stampa)')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('content_data.press_kit_3_icon')
                                                ->label('Icona/Emoji')
                                                ->placeholder('es. 📄'),
                                            Forms\Components\TextInput::make('content_data.press_kit_3_title')
                                                ->label('Titolo')
                                                ->placeholder('es. Cartella Stampa Ufficiale'),
                                            Forms\Components\TextInput::make('content_data.press_kit_3_format')
                                                ->label('Formato/Peso')
                                                ->placeholder('es. PDF — 8 MB'),
                                        ]),
                                    Forms\Components\Textarea::make('content_data.press_kit_3_description')
                                        ->label('Descrizione Press Kit 3')
                                        ->rows(2),
                                    self::pressKitFileField(3),
                                ])->columns(1),

                            Forms\Components\Fieldset::make('Press Kit 4 (Guida Media LVF)')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('content_data.press_kit_4_icon')
                                                ->label('Icona/Emoji')
                                                ->placeholder('es. 📊'),
                                            Forms\Components\TextInput::make('content_data.press_kit_4_title')
                                                ->label('Titolo')
                                                ->placeholder('es. Guida Media LVF'),
                                            Forms\Components\TextInput::make('content_data.press_kit_4_format')
                                                ->label('Formato/Peso')
                                                ->placeholder('es. PDF — 3 MB'),
                                        ]),
                                    Forms\Components\Textarea::make('content_data.press_kit_4_description')
                                        ->label('Descrizione Press Kit 4')
                                        ->rows(2),
                                    self::pressKitFileField(4),
                                ])->columns(1),
                        ]),

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
                                                ->label('Nome Completo')
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
                                                ->label('Nome Completo')
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
                                                ->label('Nome Completo')
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
                ])
                ->columnSpanFull(),
        ];
    }
}
