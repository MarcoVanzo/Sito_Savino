<?php

namespace App\Filament\Pages\Settings;

use App\Enums\PaymentGateway;
use App\Filament\Resources\PageResource;
use App\Models\SiteSetting;
use App\Services\AvvisoNuovoOrdine;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;

/**
 * Impostazioni operative dello Shop e delle Aste.
 *
 * Espone le chiavi SiteSetting dei gruppi `shop` e `auctions`
 * (seedate da ShopSettingsSeeder) che finora erano modificabili
 * solo via database. NON gestisce le chiavi `shop.support_*` /
 * `shop.size_guides`, curate dalla pagina "Guida Taglie & Contatti".
 */
class ShopSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Impostazioni Shop & Aste';

    protected static ?string $title = 'Impostazioni Shop & Aste';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'settings/shop';

    /**
     * Le chiavi in cui "vuoto" è una scelta, non una mancanza.
     *
     * Senza soglia qui vale quella di ciascuna zona di spedizione
     * (ShippingZone::sogliaGratuita). Proporre i 50 € del seeder
     * significherebbe scriverli al primo Salva e cambiare la soglia di tutte
     * le zone senza che nessuno l'abbia deciso.
     *
     * @var list<string>
     */
    private const SENZA_RIPIEGO = ['shop.free_shipping_threshold'];

    /**
     * I valori di partenza delle chiavi che non sono ancora in archivio.
     *
     * In produzione le righe di `shop.*` non c'erano tutte: gli interruttori
     * si aprivano spenti e i numeri vuoti, e il primo Salva li scriveva così.
     * Accendendo le aste, la redazione ha spento il negozio.
     *
     * @return array<string, mixed>
     */
    protected function valoriPredefiniti(): array
    {
        $predefiniti = [];

        foreach (SiteSetting::definizioniDelloShop() as $impostazione) {
            if (in_array($impostazione['key'], self::SENZA_RIPIEGO, true)) {
                continue;
            }

            data_set(
                $predefiniti,
                $impostazione['key'],
                $impostazione['type'] === 'boolean'
                    ? filter_var($impostazione['value'], FILTER_VALIDATE_BOOLEAN)
                    : $impostazione['value'],
            );
        }

        return $predefiniti;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Stato Shop')
                    ->description('Attiva/disattiva lo shop e i messaggi mostrati ai clienti.')
                    ->icon('heroicon-o-power')
                    ->schema([
                        Toggle::make('shop.enabled')
                            ->label('Shop attivo')
                            ->helperText('Se disattivo, i visitatori vedono la pagina di manutenzione.'),
                        Textarea::make('shop.maintenance_message')
                            ->label('Messaggio di manutenzione')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('shop.announcement_banner')
                            ->label('Banner promozionale')
                            ->helperText('Mostrato in cima allo shop, es. "Saldi estivi -20%!". Lascia vuoto per nasconderlo.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(1),

                Section::make('Testata dello Shop')
                    ->description('Video di sfondo della striscia blu in cima allo shop. Senza video resta lo sfondo blu.')
                    ->icon('heroicon-o-film')
                    ->schema([
                        // Nessun `disk()`: vale quello del pannello (Spaces in
                        // produzione), come per le guide taglie. La copertina,
                        // come ogni FileUpload, passa da FotoAlleggerita.
                        FileUpload::make('shop.hero_video')
                            ->label('Video di sfondo (MP4)')
                            ->helperText('MP4 (H.264), muto, in loop, parte anche sul telefono. 720p, 10-20 secondi, sotto i 5 MB: lo shop deve aprirsi veloce anche in 4G. Meglio riprese scure o poco contrastate: sopra ci va il testo.')
                            ->acceptedFileTypes(['video/mp4'])
                            ->directory('shop')
                            // 8 MB: il consiglio è 5, il margine serve a non
                            // respingere un file appena sopra. Oltre, il
                            // telefono di un tifoso in 4G paga la testata
                            // prima di vedere un prodotto.
                            ->maxSize(8192),
                        FileUpload::make('shop.hero_video_poster')
                            ->label('Immagine di copertina')
                            ->helperText('Si vede mentre il video carica e a chi ha chiesto al sistema meno animazioni. Meglio un fotogramma del video, 1600 px di larghezza.')
                            ->image()
                            ->directory('shop')
                            ->maxSize(2048),
                    ])->columns(2),

                Section::make('Carrello & Ordini')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        TextInput::make('shop.free_shipping_threshold')
                            ->label('Soglia spedizione gratuita (€)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Se compilata vale per tutte le zone di spedizione al posto della loro soglia, in carrello, al checkout (shop e aste) e nella pagina Spedizioni. Lascia vuoto per usare la soglia di ciascuna zona.'),
                        TextInput::make('shop.default_item_weight_kg')
                            ->label('Peso di ripiego per articolo (kg)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Usato per i prodotti senza peso in scheda, quando la zona di spedizione ha fasce di peso.'),
                        TextInput::make('shop.max_qty_per_product')
                            ->label('Quantità max per prodotto')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('shop.cart_expiry_days')
                            ->label('Scadenza carrello (giorni)')
                            ->numeric()
                            ->minValue(1),
                    ])->columns(2),

                Section::make('Pagamenti')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        TextInput::make('shop.active_payment_gateways')
                            ->label('Gateway di pagamento attivi')
                            // Un gateway acceso qui ma senza credenziali
                            // nell'ambiente non compare nel checkout: meglio
                            // dirlo, invece di lasciar credere che sia attivo.
                            ->helperText(fn (): string => 'Valori separati da virgola. Ammessi: stripe, paypal, bank_transfer. Valgono anche per il vincitore di un\'asta.'.self::avvisoCredenziali())
                            ->columnSpanFull(),
                        TextInput::make('shop.bank_transfer_iban')
                            ->label('IBAN per bonifico'),
                        TextInput::make('shop.bank_transfer_beneficiary')
                            ->label('Intestatario conto'),
                        TextInput::make('shop.bank_transfer_expiry_days')
                            ->label('Scadenza bonifico (giorni)')
                            ->helperText('Giorni di calendario dall\'ordine, scritti al cliente nell\'email di conferma. Poi l\'ordine si annulla; per un\'asta il lotto passa al secondo offerente.')
                            ->numeric()
                            ->minValue(1),
                    ])->columns(2),

                Section::make('Avviso nuovi ordini')
                    ->description('A ogni acquisto parte un\'email con il riepilogo e la ricevuta in PDF. Con il bonifico parte quando l\'ordine è fatto, prima dell\'accredito.')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        TextInput::make(AvvisoNuovoOrdine::IMPOSTAZIONE)
                            ->label('Indirizzi email')
                            ->placeholder('shop@savinodelbenevolley.it')
                            ->helperText('Uno o più indirizzi, separati da virgola. Vuoto: nessun avviso.')
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                foreach (array_filter(preg_split('/[\s,;]+/', (string) $value) ?: []) as $indirizzo) {
                                    if (filter_var($indirizzo, FILTER_VALIDATE_EMAIL) === false) {
                                        $fail("«{$indirizzo}» non è un indirizzo email valido.");

                                        return;
                                    }
                                }
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Ricevuta')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Textarea::make('shop.receipt_footer_text')
                            ->label('Testo in fondo alla ricevuta PDF')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Aste')
                    ->description('Configurazione della sezione aste di beneficenza.')
                    ->icon('heroicon-o-gift')
                    ->schema([
                        Toggle::make('auctions.enabled')
                            ->label('Aste attive')
                            ->columnSpanFull(),
                        TextInput::make('auctions.min_bid_increment')
                            ->label('Incremento minimo offerta (€)')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('auctions.max_bid_jump')
                            ->label('Salto massimo offerta (€)')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('auctions.payment_deadline_hours')
                            ->label('Scadenza pagamento vincitore (ore)')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('auctions.anti_snipe_minutes')
                            ->label('Anti-sniping (minuti)')
                            ->numeric()
                            ->minValue(0),
                        // Il regolamento e' diventato una pagina (Pagine > Regolamento
                        // aste): tradotta, con l'editor, e linkabile dall'elenco
                        // delle aste. L'impostazione resta solo come ripiego.
                        Placeholder::make('regolamento_aste')
                            ->label('Regolamento aste')
                            ->content(fn () => new HtmlString(
                                'Il regolamento si modifica come le altre pagine legali dello shop, in '
                                .'<a href="'.e(PageResource::getUrl('index')).'" class="underline text-primary-600">Pagine</a>'
                                .' (Regolamento aste, Condizioni di vendita, Spedizioni, Resi e rimborsi).'
                            ))
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    /**
     * I gateway senza credenziali nell'ambiente: accesi o no, il checkout non
     * li mostra, perche' aprirebbero un ordine che non si puo' pagare.
     */
    private static function avvisoCredenziali(): string
    {
        $senzaChiavi = collect(PaymentGateway::cases())
            ->reject(fn (PaymentGateway $gateway) => $gateway->configurato())
            ->map(fn (PaymentGateway $gateway) => $gateway->value)
            ->implode(', ');

        return $senzaChiavi === ''
            ? ''
            : " Senza credenziali configurate (non compaiono nel checkout): {$senzaChiavi}.";
    }
}
