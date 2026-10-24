<?php

namespace App\Filament\Forms\Templates;

use App\Filament\Forms\EtichetteDeiCampi;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;

/**
 * Il form delle pagine Biglietteria e Campagna Abbonamenti.
 *
 * Le due pagine condividono il template, ma non mostrano le stesse cose: la
 * biglietteria non ha un listino (i prezzi cambiano di partita in partita e
 * stanno su Vivaticket) e vive di uno spazio in evidenza con testo, grafica
 * e pulsante, piu' quello della Gift Card; la campagna abbonamenti ha il
 * listino, i vantaggi per gli abbonati e — quando serve — le fasi di
 * conferma e prelazione. Ogni blocco compare online solo se compilato.
 */
class TicketingTemplateForm
{
    private const DIRECTORY = 'ticketing';

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('content_data.hero_label')
                ->label(EtichetteDeiCampi::HERO_BADGE),
            Textarea::make('content_data.hero_subtitle')
                ->label(EtichetteDeiCampi::HERO_SUBTITLE),
            self::biglietteriaOnline(),
            self::spazioInEvidenza(),
            self::vantaggi(),
            self::fasiDellaCampagna(),
            self::giftCard(),
            self::listino(),
            self::informazioni(),
        ];
    }

    private static function biglietteriaOnline(): Fieldset
    {
        return Fieldset::make('Biglietteria online (Vivaticket)')
            ->schema([
                TextInput::make('content_data.tickets_url')
                    ->label('Link alla biglietteria')
                    ->url()
                    ->placeholder('https://www.vivaticket.com/it/...')
                    ->helperText('Indirizzo della pagina di vendita. Se vuoto, il pulsante non viene mostrato.')
                    ->columnSpanFull(),
                TextInput::make('content_data.tickets_button_text')
                    ->label(EtichetteDeiCampi::BUTTON_TEXT)
                    ->placeholder('es. Acquista su Vivaticket'),
                TextInput::make('content_data.tickets_note')
                    ->label('Nota sotto al pulsante')
                    ->placeholder('es. Vendita gestita da Vivaticket'),
            ])
            ->columns(2);
    }

    /**
     * Un solo spazio con testo, grafica e pulsante: e' quello che la
     * biglietteria mostra al posto del listino, e dove la campagna
     * abbonamenti mette la grafica della stagione.
     */
    private static function spazioInEvidenza(): Fieldset
    {
        return Fieldset::make('Spazio in evidenza (testo, grafica e pulsante)')
            ->schema([
                TextInput::make('content_data.feature_title')
                    ->label('Titolo')
                    ->placeholder('es. Biglietti per le gare casalinghe'),
                FileUpload::make('content_data.feature_image')
                    ->label('Grafica')
                    ->image()
                    ->directory(self::DIRECTORY)
                    ->maxSize(4096)
                    ->helperText('Consigliata almeno 1600 px di larghezza; con il testo accanto occupa meta\' riga, da sola tutta la larghezza.'),
                Textarea::make('content_data.feature_text')
                    ->label('Testo')
                    ->rows(5)
                    ->helperText('Un a capo vuoto separa i paragrafi.')
                    ->columnSpanFull(),
                TextInput::make('content_data.feature_button_text')
                    ->label(EtichetteDeiCampi::BUTTON_TEXT)
                    ->placeholder('es. Acquista i biglietti'),
                TextInput::make('content_data.feature_button_url')
                    ->label('Link del pulsante')
                    ->url()
                    ->placeholder('es. https://savinodelbenevolley.vivaticket.it/...')
                    ->helperText('Senza link il pulsante non compare.'),
            ])
            ->columns(2);
    }

    private static function vantaggi(): Fieldset
    {
        return Fieldset::make('Vantaggi per gli abbonati')
            ->schema([
                TextInput::make('content_data.benefits_heading')
                    ->label('Titolo della sezione')
                    ->placeholder('es. Vantaggi')
                    ->columnSpanFull(),
                Repeater::make('content_data.benefits')
                    ->label('Elenco dei vantaggi')
                    ->schema([
                        // Editor e non area di testo: la redazione evidenzia in
                        // grassetto una parte della frase (l'importo, la data,
                        // il nome del partner), e in una Textarea ne' Ctrl+B ne'
                        // il copia-incolla da un documento lasciano formattazione.
                        RichEditor::make('text')
                            ->label('Vantaggio')
                            ->required()
                            ->toolbarButtons(['bold', 'italic', 'link', 'undo', 'redo'])
                            ->helperText('Grassetto con il pulsante B o Ctrl+B; il corsivo e i collegamenti funzionano allo stesso modo.')
                            ->placeholder('es. Sconto del 10% sul merchandising ufficiale presentando la tessera dell\'abbonamento'),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    // L'etichetta della voce chiusa e' testo, non markup: con
                    // l'editor il valore arriva come <p>...</p>.
                    ->itemLabel(fn (array $state): ?string => self::soloTesto($state['text'] ?? null))
                    ->createItemButtonLabel('Aggiungi vantaggio'),
            ]);
    }

    /**
     * Prelazione per gli ex abbonati, cambio posto, nuovi abbonati: le fasi
     * della campagna con le loro date. Vuote, la sezione non compare.
     */
    private static function fasiDellaCampagna(): Fieldset
    {
        return Fieldset::make('Fasi della campagna (conferme e prelazioni)')
            ->schema([
                TextInput::make('content_data.phases_heading')
                    ->label('Titolo della sezione')
                    ->placeholder('es. Le fasi della campagna')
                    ->columnSpanFull(),
                Repeater::make('content_data.phases')
                    ->label('Fasi')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->placeholder('es. Prelazione ex abbonati'),
                        TextInput::make('period')
                            ->label('Periodo')
                            ->placeholder('es. Dal 28 luglio al 18 agosto'),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(4)
                            ->placeholder('es. Sara\' possibile confermare il proprio posto al Pala BigMat scrivendo a ticketing@...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->createItemButtonLabel('Aggiungi fase'),
            ]);
    }

    private static function giftCard(): Fieldset
    {
        return Fieldset::make('Gift Card')
            ->schema([
                TextInput::make('content_data.gift_card_title')
                    ->label('Titolo')
                    ->placeholder('es. Gift Card'),
                FileUpload::make('content_data.gift_card_image')
                    ->label('Grafica (390 × 390 px)')
                    ->image()
                    ->directory(self::DIRECTORY)
                    ->maxSize(2048)
                    ->helperText('Quadrata, 390 × 390 px.'),
                Textarea::make('content_data.gift_card_text')
                    ->label('Come funziona')
                    ->rows(4)
                    ->placeholder('es. Scegli l\'importo, ricevi il codice via email e usalo su Vivaticket per biglietti e abbonamenti.')
                    ->columnSpanFull(),
                TextInput::make('content_data.gift_card_button_text')
                    ->label(EtichetteDeiCampi::BUTTON_TEXT)
                    ->placeholder('es. Acquista la Gift Card'),
                TextInput::make('content_data.gift_card_url')
                    ->label('Link di acquisto')
                    ->url()
                    ->placeholder('es. https://savinodelbenevolley.vivaticket.it/it/vivacard/prices'),
            ])
            ->columns(2);
    }

    private static function listino(): Fieldset
    {
        return Fieldset::make('Listino abbonamenti')
            ->schema([
                TextInput::make('content_data.plans_heading')
                    ->label('Titolo della sezione'),
                TextInput::make('content_data.popular_badge')
                    ->label('Testo Badge "Più Popolare"'),
                Textarea::make('content_data.plans_empty')
                    ->label('Testo quando non ci sono listini pubblicati')
                    ->helperText('Vuoto, la sezione sparisce del tutto quando non ci sono piani.')
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make('content_data.plans')
                    ->label('Piani e Abbonamenti')
                    ->schema([
                        TextInput::make('name')->label('Nome Piano')->required(),
                        TextInput::make('price')->label('Prezzo intero (€)')->required(),
                        TextInput::make('period')->label('Periodo (es. a partita, stagione)')->required(),
                        // Lo stesso posto ha piu' tariffe: senza questi campi il listino
                        // della societa' non ci stava dentro. Lasciandoli vuoti la scheda
                        // mostra il solo prezzo intero.
                        TextInput::make('price_returning')->label('Tariffa riconferma (€)'),
                        TextInput::make('price_under16')->label('Tariffa Under 16 (€)'),
                        TagsInput::make('features')->label('Vantaggi (Premi invio)'),
                        Toggle::make('highlight')->label('Evidenziato (Più Popolare)'),
                        TextInput::make('cta')->label('Testo Pulsante (es. Acquista)'),
                        TextInput::make('cta_url')
                            ->label('Link Pulsante (URL acquisto/abbonamento)')
                            ->url()
                            ->placeholder('es. https://www.vivaticket.com/...'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
            ])
            ->columns(2);
    }

    /**
     * L'etichetta di una voce chiusa del ripetitore: solo il testo, senza i tag
     * dell'editor.
     */
    private static function soloTesto(mixed $valore): ?string
    {
        if (! is_string($valore)) {
            return null;
        }

        $testo = trim(html_entity_decode(strip_tags($valore), ENT_QUOTES | ENT_HTML5));

        return $testo !== '' ? $testo : null;
    }

    private static function informazioni(): Fieldset
    {
        return Fieldset::make('Informazioni sull\'acquisto')
            ->schema([
                TextInput::make('content_data.info_heading')
                    ->label('Titolo della sezione')
                    ->columnSpanFull(),
                TextInput::make('content_data.online_title')
                    ->label('Titolo Info Online'),
                TextInput::make('content_data.boxoffice_title')
                    ->label('Titolo Info Botteghino')
                    ->helperText('Lasciando vuoti titolo e descrizione il riquadro del botteghino non compare.'),
                Textarea::make('content_data.online_description')
                    ->label('Descrizione Info Online'),
                Textarea::make('content_data.boxoffice_description')
                    ->label('Descrizione Info Botteghino'),
            ])
            ->columns(2);
    }
}
