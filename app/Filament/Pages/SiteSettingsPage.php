<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Sito Web';
    protected static ?string $navigationLabel = 'Impostazioni Sito';
    protected static ?string $title = 'Impostazioni Sito';
    protected static ?int $navigationSort = 50;
    protected static string $view = 'filament.pages.site-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = SiteSetting::getAllCached();
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
                                TextInput::make('contact_email')->label('Email')->email(),
                                TextInput::make('contact_phone')->label('Telefono'),
                                TextInput::make('contact_pec')->label('PEC')->email(),
                                TextInput::make('contact_address')->label('Indirizzo'),
                                TextInput::make('contact_city')->label('Città'),
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
                                TextInput::make('home_hero_title')->label('Titolo'),
                                TextInput::make('home_hero_title_accent')->label('Accento'),
                                TextInput::make('home_hero_claim')->label('Claim'),
                            ]),
                            Section::make('CTA Hero')->schema([
                                TextInput::make('home_cta_primary_text')->label('CTA Primario'),
                                TextInput::make('home_cta_primary_url')->label('URL Primario'),
                                TextInput::make('home_cta_secondary_text')->label('CTA Secondario'),
                                TextInput::make('home_cta_secondary_url')->label('URL Secondario'),
                            ])->columns(2),
                            Section::make('Stats')->schema([
                                Textarea::make('home_stats')->label('Statistiche (JSON)')->rows(5),
                            ]),
                            Section::make('Banners')->schema([
                                TextInput::make('home_cta_ticketing_title')->label('Ticketing Titolo'),
                                TextInput::make('home_cta_ticketing_text')->label('Ticketing Testo'),
                                TextInput::make('home_cta_ticketing_url')->label('Ticketing URL'),
                                TextInput::make('home_cta_shop_title')->label('Shop Titolo'),
                                TextInput::make('home_cta_shop_text')->label('Shop Testo'),
                                TextInput::make('home_cta_shop_url')->label('Shop URL'),
                            ])->columns(2),
                        ]),
                    ])->persistTabInQueryString()->columnSpanFull(),
            ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            if ($value !== null) {
                SiteSetting::set($key, $value);
            }
        }
        Notification::make()->title('Impostazioni salvate con successo')->success()->send();
    }
}
