<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\UnderConstruction;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Hex('#003063'), // Savino Blue
                'danger' => Color::Hex('#DF338F'), // Savino Red
                'warning' => Color::Hex('#bda871'), // Savino Gold
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Outfit')
            ->brandName('Savino Del Bene')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/logo.png'))
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                NavigationGroup::make()->label('Stagione')->icon('heroicon-o-trophy'),
                NavigationGroup::make()->label('Società')->icon('heroicon-o-building-office-2'),
                NavigationGroup::make()->label('Ticketing')->icon('heroicon-o-ticket'),
                NavigationGroup::make()->label('Sponsor')->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make()->label('SDB Youth')->icon('heroicon-o-academic-cap'),
                NavigationGroup::make()->label('Summer Camp')->icon('heroicon-o-sun'),
                NavigationGroup::make()->label('Sociale')->icon('heroicon-o-heart'),
                NavigationGroup::make()->label('Comunicazione')->icon('heroicon-o-megaphone'),
                NavigationGroup::make()->label('Shop Ufficiale')->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make()->label('Pagine & Extra')->icon('heroicon-o-document-duplicate')->collapsed(),
                NavigationGroup::make()->label('Amministrazione')->icon('heroicon-o-cog-8-tooth')->collapsed(),
            ])
            ->navigationItems([
                // Stagione
                NavigationItem::make('CEV Champions League')->group('Stagione')->url(fn () => UnderConstruction::getUrl())->sort(4),
                NavigationItem::make('Coppa Italia & Playoff')->group('Stagione')->url(fn () => UnderConstruction::getUrl())->sort(5),

                // Società
                NavigationItem::make('Storia')->group('Società')->url(fn () => UnderConstruction::getUrl())->sort(2),
                NavigationItem::make('Safeguarding')->group('Società')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Contatti')->group('Società')->url(fn () => UnderConstruction::getUrl())->sort(4),
                NavigationItem::make('Palazzetto & Google Maps')->group('Società')->url(fn () => UnderConstruction::getUrl())->sort(5),

                // Ticketing
                NavigationItem::make('Biglietteria')->group('Ticketing')->url(fn () => UnderConstruction::getUrl())->sort(1),
                NavigationItem::make('Campagna Abbonamenti')->group('Ticketing')->url(fn () => UnderConstruction::getUrl())->sort(2),
                NavigationItem::make('Accessibilità & Info')->group('Ticketing')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Convenzioni')->group('Ticketing')->url(fn () => UnderConstruction::getUrl())->sort(4),

                // Sponsor
                NavigationItem::make('Diventa Sponsor')->group('Sponsor')->url(fn () => UnderConstruction::getUrl())->sort(2),
                NavigationItem::make('Title Sponsor (SDB)')->group('Sponsor')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Hospitality')->group('Sponsor')->url(fn () => UnderConstruction::getUrl())->sort(4),

                // SDB Youth
                NavigationItem::make('Settore Giovanile')->group('SDB Youth')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Talent Day & Recruiting')->group('SDB Youth')->url(fn () => UnderConstruction::getUrl())->sort(4),
                NavigationItem::make('Progetto Affiliazioni')->group('SDB Youth')->url(fn () => UnderConstruction::getUrl())->sort(5),

                // Summer Camp
                NavigationItem::make('Tutte le Info')->group('Summer Camp')->url(fn () => UnderConstruction::getUrl())->sort(1),
                NavigationItem::make('Iscrizione (Experience)')->group('Summer Camp')->url(fn () => UnderConstruction::getUrl())->sort(2),

                // Sociale
                NavigationItem::make('Progetti Sociali')->group('Sociale')->url(fn () => UnderConstruction::getUrl())->sort(1),
                NavigationItem::make('Volley 4 All')->group('Sociale')->url(fn () => UnderConstruction::getUrl())->sort(2),
                NavigationItem::make('Bilancio Sostenibilità')->group('Sociale')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Progetto Scuola')->group('Sociale')->url(fn () => UnderConstruction::getUrl())->sort(4),

                // Comunicazione
                NavigationItem::make('Cartelle Stampa')->group('Comunicazione')->url(fn () => UnderConstruction::getUrl())->sort(2),
                NavigationItem::make('Magazine')->group('Comunicazione')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Double Face')->group('Comunicazione')->url(fn () => UnderConstruction::getUrl())->sort(4),

                // Shop Ufficiale
                NavigationItem::make('Kit Gara')->group('Shop Ufficiale')->url(fn () => UnderConstruction::getUrl())->sort(1),
                NavigationItem::make('Abbigliamento & Accessori')->group('Shop Ufficiale')->url(fn () => UnderConstruction::getUrl())->sort(2),
                NavigationItem::make('Aste & Outlet')->group('Shop Ufficiale')->url(fn () => UnderConstruction::getUrl())->sort(3),
                NavigationItem::make('Guida Taglie & Contatti')->group('Shop Ufficiale')->url(fn () => UnderConstruction::getUrl())->sort(4),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Vai al Sito Pubblico')
                    ->url('/')
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // I widget sono auto-discoverati e ordinati tramite la proprietà $sort nelle rispettive classi.
                // Rimuoviamo i widget di default di Filament se non necessari, oppure li lasciamo vuoti.
            ])
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['it', 'en'])
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('filament.hooks.footer')->render()
            )
            ->databaseNotifications()
            ->maxContentWidth('full')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
