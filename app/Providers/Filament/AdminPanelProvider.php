<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
                'danger' => Color::Hex('#CD1719'), // Savino Red
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
                \Filament\Navigation\NavigationGroup::make()->label('Stagione')->icon('heroicon-o-trophy'),
                \Filament\Navigation\NavigationGroup::make()->label('Società')->icon('heroicon-o-building-office-2'),
                \Filament\Navigation\NavigationGroup::make()->label('Ticketing')->icon('heroicon-o-ticket'),
                \Filament\Navigation\NavigationGroup::make()->label('Sponsor')->icon('heroicon-o-currency-dollar'),
                \Filament\Navigation\NavigationGroup::make()->label('SDB Youth')->icon('heroicon-o-academic-cap'),
                \Filament\Navigation\NavigationGroup::make()->label('Summer Camp')->icon('heroicon-o-sun'),
                \Filament\Navigation\NavigationGroup::make()->label('Sociale')->icon('heroicon-o-heart'),
                \Filament\Navigation\NavigationGroup::make()->label('Comunicazione')->icon('heroicon-o-megaphone'),
                \Filament\Navigation\NavigationGroup::make()->label('Shop Ufficiale')->icon('heroicon-o-shopping-bag'),
                \Filament\Navigation\NavigationGroup::make()->label('Pagine & Extra')->icon('heroicon-o-document-duplicate')->collapsed(),
                \Filament\Navigation\NavigationGroup::make()->label('Amministrazione')->icon('heroicon-o-cog-8-tooth')->collapsed(),
            ])
            ->navigationItems([
                // Stagione
                \Filament\Navigation\NavigationItem::make('Foto Ufficiale')->group('Stagione')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('CEV Champions League')->group('Stagione')->url(fn () => url('/admin/pages'))->sort(4),
                \Filament\Navigation\NavigationItem::make('Coppa Italia & Playoff')->group('Stagione')->url(fn () => url('/admin/pages'))->sort(5),
                
                // Società
                \Filament\Navigation\NavigationItem::make('Storia')->group('Società')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Safeguarding')->group('Società')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Contatti')->group('Società')->url(fn () => url('/admin/pages'))->sort(4),
                \Filament\Navigation\NavigationItem::make('Palazzetto & Google Maps')->group('Società')->url(fn () => url('/admin/pages'))->sort(5),
                
                // Ticketing
                \Filament\Navigation\NavigationItem::make('Biglietteria')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Campagna Abbonamenti')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Accessibilità & Info')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Convenzioni')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(4),
                
                // Sponsor
                \Filament\Navigation\NavigationItem::make('Diventa Sponsor')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Title Sponsor (SDB)')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Hospitality')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(4),
                
                // SDB Youth
                \Filament\Navigation\NavigationItem::make('Serie U17 & U15')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Settore Giovanile')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Talent Day & Recruiting')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(4),
                \Filament\Navigation\NavigationItem::make('Progetto Affiliazioni')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(5),
                
                // Summer Camp
                \Filament\Navigation\NavigationItem::make('Tutte le Info')->group('Summer Camp')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Iscrizione (Experience)')->group('Summer Camp')->url(fn () => url('/admin/pages'))->sort(2),
                
                // Sociale
                \Filament\Navigation\NavigationItem::make('Progetti Sociali')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Volley 4 All')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Bilancio Sostenibilità')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Progetto Scuola')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(4),
                
                // Comunicazione
                \Filament\Navigation\NavigationItem::make('Cartelle Stampa')->group('Comunicazione')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Magazine')->group('Comunicazione')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Double Face')->group('Comunicazione')->url(fn () => url('/admin/pages'))->sort(4),
                
                // Shop Ufficiale
                \Filament\Navigation\NavigationItem::make('Kit Gara')->group('Shop Ufficiale')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Abbigliamento & Accessori')->group('Shop Ufficiale')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Aste & Outlet')->group('Shop Ufficiale')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Guida Taglie & Contatti')->group('Shop Ufficiale')->url(fn () => url('/admin/pages'))->sort(4),
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Vai al Sito Pubblico')
                    ->url('/')
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // I widget sono auto-discoverati e ordinati tramite la proprietà $sort nelle rispettive classi.
                // Rimuoviamo i widget di default di Filament se non necessari, oppure li lasciamo vuoti.
            ])
            ->plugin(
                \Filament\SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['it', 'en'])
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::FOOTER,
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
