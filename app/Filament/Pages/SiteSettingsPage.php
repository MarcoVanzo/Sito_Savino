<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\SiteSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $navigationGroup = 'Amministrazione';

    protected static ?string $title = 'Impostazioni Sito';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'settings';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->role === UserRole::SuperAdmin;
    }

    protected static string $view = 'filament.pages.site-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $flat = SiteSetting::getAllCached();
        $this->data = [];
        foreach ($flat as $key => $value) {
            data_set($this->data, $key, $value);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Impostazioni')
                    ->tabs([
                        Tab::make('Generali')->icon('heroicon-o-globe-alt')->schema([
                            Section::make('Identità Sito')->schema([
                                TextInput::make('site_name')->label('Nome Sito'),
                                Textarea::make('site_description')->label('Descrizione Sito')->rows(3),
                                TextInput::make('site_logo')->label('Logo Sito')->helperText('Percorso immagine'),
                            ])->columns(1),
                            Section::make('Corporate')->schema([
                                TextInput::make('corporate_logo')->label('Logo Corporate'),
                                TextInput::make('corporate_url')->label('URL Corporate')->url(),
                                TextInput::make('corporate_name')->label('Nome Corporate'),
                                TextInput::make('corporate_description')->label('Descrizione Corporate'),
                            ])->columns(2),
                        ]),
                        Tab::make('Contatti')->icon('heroicon-o-phone')->schema([
                            Section::make('Principali')->schema([
                                TextInput::make('email')->label('Email')->email(),
                                TextInput::make('phone')->label('Telefono'),
                                TextInput::make('pec')->label('PEC')->email(),
                                TextInput::make('address')->label('Indirizzo'),
                                TextInput::make('city')->label('Città'),
                                TextInput::make('office_hours')->label('Orari'),
                            ])->columns(2),
                            Section::make('Dipartimenti')->schema([
                                TextInput::make('press_email')->label('Stampa')->email(),
                                TextInput::make('social_email')->label('Social')->email(),
                                TextInput::make('media_email')->label('Media')->email(),
                                TextInput::make('youth_email')->label('Giovanili')->email(),
                            ])->columns(2),
                        ]),
                        Tab::make('Social')->icon('heroicon-o-share')->schema([
                            Section::make('Social Media')->schema([
                                TextInput::make('social_instagram')->label('Instagram')->url(),
                                TextInput::make('social_facebook')->label('Facebook')->url(),
                                TextInput::make('social_youtube')->label('YouTube')->url(),
                                TextInput::make('social_x')->label('X (Twitter)')->url(),
                                TextInput::make('social_tiktok')->label('TikTok')->url(),
                            ]),
                        ]),
                        Tab::make('Footer')->icon('heroicon-o-document-text')->schema([
                            Textarea::make('footer_tagline')->label('Tagline')->rows(3),
                            TextInput::make('footer_copyright')->label('Copyright')->helperText('{year} = anno dinamico'),
                            TextInput::make('footer_piva')->label('P.IVA'),
                        ]),
                        Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                            TextInput::make('seo_default_title')->label('Titolo Default'),
                            Textarea::make('seo_default_description')->label('Descrizione Default')->rows(3),
                            TextInput::make('seo_og_image')->label('OG Image'),
                        ]),
                        Tab::make('Aspetto')->icon('heroicon-o-swatch')->schema([
                            TextInput::make('primary_color')->label('Colore Primario')->helperText('HEX es: #C5A55A'),
                            TextInput::make('secondary_color')->label('Colore Secondario')->helperText('HEX es: #0B1521'),
                        ]),
                        Tab::make('Homepage')->icon('heroicon-o-home')->schema([
                            Section::make('Hero')->schema([
                                TextInput::make('hero_title')->label('Titolo'),
                                TextInput::make('hero_subtitle')->label('Accento'),
                                TextInput::make('hero_tagline')->label('Claim'),
                            ]),
                            Section::make('CTA Hero')->schema([
                                TextInput::make('hero_cta1_label')->label('CTA Primario'),
                                TextInput::make('hero_cta1_url')->label('URL Primario'),
                                TextInput::make('hero_cta2_label')->label('CTA Secondario'),
                                TextInput::make('hero_cta2_url')->label('URL Secondario'),
                            ])->columns(2),
                            Section::make('Stats')->schema([
                                Textarea::make('stats')->label('Statistiche (JSON)')->rows(5),
                                TextInput::make('stats_title')->label('Titolo Sezione'),
                                TextInput::make('stats_subtitle')->label('Sottotitolo Sezione'),
                            ]),
                            Section::make('Banners')->schema([
                                TextInput::make('cta_ticketing_title')->label('Ticketing Titolo'),
                                TextInput::make('cta_ticketing_text')->label('Ticketing Testo'),
                                TextInput::make('cta_ticketing_url')->label('Ticketing URL'),
                                TextInput::make('cta_shop_title')->label('Shop Titolo'),
                                TextInput::make('cta_shop_text')->label('Shop Testo'),
                                TextInput::make('cta_shop_url')->label('Shop URL'),
                            ])->columns(2),
                        ]),
                    Tab::make('Shop')->icon('heroicon-o-shopping-bag')->schema([
                        Section::make('Configurazione Generale')->schema([
                            Toggle::make('shop.enabled')->label('Shop Attivo'),
                            Textarea::make('shop.maintenance_message')->label('Messaggio Manutenzione')->rows(2),
                            Textarea::make('shop.announcement_banner')->label('Banner Promozionale')->rows(2),
                            TextInput::make('shop.contact_email')->label('Email Contatto Shop')->email(),
                            TextInput::make('shop.max_qty_per_product')->label('Quantità Max per Prodotto')->numeric(),
                            TextInput::make('shop.cart_expiry_days')->label('Scadenza Carrello (giorni)')->numeric(),
                            TextInput::make('shop.free_shipping_threshold')->label('Soglia Spedizione Gratuita (€)')->numeric()->prefix('€'),
                        ])->columns(2),
                        Section::make('Metodi di Pagamento')->schema([
                            Toggle::make('shop.stripe_enabled')->label('Stripe (Carta di Credito)'),
                            Toggle::make('shop.paypal_enabled')->label('PayPal'),
                            Toggle::make('shop.bank_transfer_enabled')->label('Bonifico Bancario'),
                            TextInput::make('shop.bank_transfer_iban')->label('IBAN'),
                            TextInput::make('shop.bank_transfer_beneficiary')->label('Intestatario Conto'),
                            TextInput::make('shop.bank_transfer_expiry_days')->label('Scadenza Bonifico (giorni)')->numeric(),
                        ])->columns(2),
                        Section::make('Documentazione')->schema([
                            Textarea::make('shop.return_policy_text')->label('Policy Resi')->rows(3),
                            TextInput::make('shop.receipt_footer_text')->label('Footer Ricevuta PDF'),
                        ]),
                    ]),
                    Tab::make('Aste')->icon('heroicon-o-fire')->schema([
                        Section::make('Configurazione Aste')->schema([
                            Toggle::make('auctions.enabled')->label('Aste Attive'),
                            TextInput::make('auctions.min_bid_increment')->label('Incremento Minimo (€)')->numeric()->prefix('€'),
                            TextInput::make('auctions.max_bid_jump')->label('Salto Massimo (€)')->numeric()->prefix('€'),
                            TextInput::make('auctions.payment_deadline_hours')->label('Scadenza Pagamento Vincitore (ore)')->numeric(),
                            TextInput::make('auctions.anti_snipe_minutes')->label('Anti-Sniping (minuti)')->numeric(),
                        ])->columns(2),
                        Section::make('Regolamento')->schema([
                            Textarea::make('auctions.rules_text')->label('Testo Regolamento Aste')->rows(5),
                        ]),
                    ]),
                    ])->persistTabInQueryString()->columnSpanFull(),
            ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $flat = \Illuminate\Support\Arr::dot($data);
        foreach ($flat as $key => $value) {
            if ($value !== null) {
                SiteSetting::set($key, $value);
            }
        }
        Notification::make()->title('Impostazioni salvate con successo')->success()->send();
    }
}
