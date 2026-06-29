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
