<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\SiteSetting;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Support\Arr;

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
            if ($key === 'shop.active_payment_gateways' && is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
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
                        Tab::make('Homepage')->icon('heroicon-o-home')->schema([
                            Section::make('Hero')->schema([
                                TextInput::make('hero_title')->label('Titolo'),
                                TextInput::make('hero_subtitle')->label('Accento'),
                                TextInput::make('hero_tagline')->label('Claim'),
                                TextInput::make('hero_video_url')->label('Video Background URL')->url()->helperText('Inserisci il link diretto a un file .mp4. Lascia vuoto per usare le immagini.'),
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
                                TextInput::make('shop.max_qty_per_product')->label('Quantità Max per Prodotto')->numeric(),
                                TextInput::make('shop.cart_expiry_days')->label('Scadenza Carrello (giorni)')->numeric(),
                                TextInput::make('shop.free_shipping_threshold')->label('Soglia Spedizione Gratuita (€)')->numeric()->prefix('€'),
                            ])->columns(2),
                            Section::make('Metodi di Pagamento')->schema([
                                \Filament\Forms\Components\CheckboxList::make('shop.active_payment_gateways')
                                    ->label('Gateway di Pagamento Attivi')
                                    ->options([
                                        'stripe' => 'Carta di Credito (Stripe)',
                                        'paypal' => 'PayPal',
                                        'bank_transfer' => 'Bonifico Bancario',
                                    ])->columns(3)->columnSpanFull(),
                                TextInput::make('shop.bank_transfer_iban')->label('IBAN'),
                                TextInput::make('shop.bank_transfer_beneficiary')->label('Intestatario Conto'),
                                TextInput::make('shop.bank_transfer_expiry_days')->label('Scadenza Bonifico (giorni)')->numeric(),
                            ])->columns(2),
                            Section::make('Documentazione')->schema([
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
                        Tab::make('Documenti Legali')->icon('heroicon-o-document-text')->schema([
                            Section::make('Privacy e Policy')->schema([
                                FileUpload::make('legal.privacy_policy')->label('Privacy Policy')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                                FileUpload::make('legal.cookie_policy')->label('Cookie Policy')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                                FileUpload::make('legal.informativa_fornitori')->label('Informativa Fornitori')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                            ])->columns(3),
                            Section::make('Corporate Governance')->schema([
                                FileUpload::make('legal.modello_organizzativo')->label('Modello Organizzativo')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                                FileUpload::make('legal.codice_tutela_minori')->label('Codice Tutela Minori')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                                FileUpload::make('legal.protocollo_bullismo')->label('Protocollo Bullismo')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                                FileUpload::make('legal.protocollo_razzismo')->label('Protocollo Razzismo')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                            ])->columns(2),
                        ]),
                    ])->persistTabInQueryString()->columnSpanFull(),
            ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        if (isset($data['shop']['active_payment_gateways']) && is_array($data['shop']['active_payment_gateways'])) {
            $data['shop']['active_payment_gateways'] = implode(',', $data['shop']['active_payment_gateways']);
        }
        $flat = Arr::dot($data);
        foreach ($flat as $key => $value) {
            SiteSetting::set($key, $value ?? '');
        }
        Notification::make()->title('Impostazioni salvate con successo')->success()->send();
    }
}
