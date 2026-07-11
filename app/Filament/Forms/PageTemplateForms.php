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
            Forms\Components\Section::make('Hero e Presentazione')
                ->schema([
                    Forms\Components\TextInput::make('content_data.hero_subtitle')
                        ->label('Sottotitolo Hero')
                        ->placeholder('es. Dal 1982'),
                    Forms\Components\Textarea::make('content_data.hero_description')
                        ->label('Descrizione Hero'),
                ])->columns(2),

            Forms\Components\Section::make('Modulo di Contatto')
                ->schema([
                    Forms\Components\TextInput::make('content_data.form_title')
                        ->label('Titolo Modulo di Contatto')
                        ->placeholder('es. Scrivici'),
                    Forms\Components\TextInput::make('content_data.form_success_message')
                        ->label('Messaggio di Successo Modulo'),
                ])->columns(2),

            Forms\Components\Section::make('Sede Legale & Dati Societari')
                ->schema([
                    Forms\Components\TextInput::make('content_data.legal_address')
                        ->label('Indirizzo Sede Legale')
                        ->placeholder('es. Via Benozzo Gozzoli, 5/6 50018 Scandicci – Firenze')
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('content_data.legal_phone')
                        ->label('Telefono Sede')
                        ->placeholder('es. 055 721503'),
                    Forms\Components\TextInput::make('content_data.legal_email')
                        ->label('Email Sede')
                        ->placeholder('es. info@savinodelbenevolley.it'),
                    Forms\Components\TextInput::make('content_data.legal_pec')
                        ->label('PEC Sede')
                        ->placeholder('es. pallavoloscandicci@legalmail.it'),
                    Forms\Components\TextInput::make('content_data.legal_sdi')
                        ->label('Codice SDI')
                        ->placeholder('es. KRRH6B9'),
                    Forms\Components\TextInput::make('content_data.legal_piva')
                        ->label('Partita IVA')
                        ->placeholder('es. 06271460484'),
                    Forms\Components\TextInput::make('content_data.legal_cf')
                        ->label('Codice Fiscale')
                        ->placeholder('es. 94217750481'),
                    Forms\Components\TextInput::make('content_data.legal_fipav')
                        ->label('Codice FIPAV')
                        ->placeholder('es. 100470331'),
                ])->columns(2),

            Forms\Components\Section::make('Mappa Sede Legale (Google Maps)')
                ->schema([
                    Forms\Components\TextInput::make('content_data.map_title')
                        ->label('Titolo Mappa (es. Sede Legale)')
                        ->placeholder('es. Palazzo Wanny'),
                    Forms\Components\TextInput::make('content_data.map_address')
                        ->label('Indirizzo per la ricerca Mappa')
                        ->placeholder('es. Via Benozzo Gozzoli, 5/6, Scandicci'),
                ])->columns(2),

            Forms\Components\Section::make('Rubrica Contatti e Dipartimenti')
                ->schema([
                    Forms\Components\Repeater::make('content_data.contacts_list')
                        ->label('Lista Contatti / Dipartimenti')
                        ->schema([
                            Forms\Components\TextInput::make('category')
                                ->label('Categoria / Sezione (es. SERIE A1, SDB VOLLEY YOUTH, ecc.)')
                                ->placeholder('es. SERIE A1'),
                            Forms\Components\TextInput::make('role')
                                ->label('Ruolo / Dipartimento')
                                ->required()
                                ->placeholder('es. DIRETTORE GENERALE'),
                            Forms\Components\TextInput::make('name')
                                ->label('Nome Referente (Opzionale)')
                                ->placeholder('es. Francesco Paoletti'),
                            Forms\Components\TextInput::make('email')
                                ->label('Email (Opzionale)')
                                ->email()
                                ->placeholder('es. press@savinodelbenevolley.it'),
                            Forms\Components\TextInput::make('phone')
                                ->label('Telefono / Cellulare (Opzionale)')
                                ->placeholder('es. 333 88 24 951'),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->createItemButtonLabel('Aggiungi Contatto / Ruolo')
                        ->grid(2),
                ]),
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
