<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

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

                Section::make('Carrello & Ordini')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        TextInput::make('shop.free_shipping_threshold')
                            ->label('Soglia spedizione gratuita (€)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('shop.max_qty_per_product')
                            ->label('Quantità max per prodotto')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('shop.cart_expiry_days')
                            ->label('Scadenza carrello (giorni)')
                            ->numeric()
                            ->minValue(1),
                    ])->columns(3),

                Section::make('Pagamenti')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        TextInput::make('shop.active_payment_gateways')
                            ->label('Gateway di pagamento attivi')
                            ->helperText('Valori separati da virgola. Ammessi: stripe, paypal, bank_transfer.')
                            ->columnSpanFull(),
                        TextInput::make('shop.bank_transfer_iban')
                            ->label('IBAN per bonifico'),
                        TextInput::make('shop.bank_transfer_beneficiary')
                            ->label('Intestatario conto'),
                        TextInput::make('shop.bank_transfer_expiry_days')
                            ->label('Scadenza bonifico (giorni)')
                            ->numeric()
                            ->minValue(1),
                    ])->columns(2),

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
                        Textarea::make('auctions.rules_text')
                            ->label('Regolamento aste')
                            ->helperText('Testo completo mostrato nella pagina delle aste.')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }
}
