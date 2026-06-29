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
                \Filament\Navigation\NavigationGroup::make()->label('SDB Youth')->icon('heroicon-o-academic-cap'),
                \Filament\Navigation\NavigationGroup::make()->label('Società')->icon('heroicon-o-building-office-2'),
                \Filament\Navigation\NavigationGroup::make()->label('Sponsor')->icon('heroicon-o-currency-dollar'),
                \Filament\Navigation\NavigationGroup::make()->label('Media')->icon('heroicon-o-megaphone'),
                \Filament\Navigation\NavigationGroup::make()->label('Shop')->icon('heroicon-o-shopping-bag'),
                \Filament\Navigation\NavigationGroup::make()->label('Ticketing')->icon('heroicon-o-ticket'),
                \Filament\Navigation\NavigationGroup::make()->label('Camp')->icon('heroicon-o-sun'),
                \Filament\Navigation\NavigationGroup::make()->label('Sociale')->icon('heroicon-o-heart'),
                \Filament\Navigation\NavigationGroup::make()->label('Pagine & Extra')->icon('heroicon-o-document-duplicate')->collapsed(),
                \Filament\Navigation\NavigationGroup::make()->label('Amministrazione')->icon('heroicon-o-cog-8-tooth')->collapsed(),
            ])
            ->navigationItems([
                // Società
                \Filament\Navigation\NavigationItem::make('Storia')->group('Società')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Palazzetto')->group('Società')->url(fn () => url('/admin/pages'))->sort(3),
                
                // Ticketing
                \Filament\Navigation\NavigationItem::make('Biglietteria')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Abbonamenti')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Convenzioni')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Accessibilità')->group('Ticketing')->url(fn () => url('/admin/pages'))->sort(4),
                
                // Sponsor
                \Filament\Navigation\NavigationItem::make('Title Sponsor')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Diventa Sponsor')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Hospitality')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(4),
                \Filament\Navigation\NavigationItem::make('Affiliazioni')->group('Sponsor')->url(fn () => url('/admin/pages'))->sort(5),
                
                // SDB Youth
                \Filament\Navigation\NavigationItem::make('Settore Giovanile')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Talent Day')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Progetto Scuola')->group('SDB Youth')->url(fn () => url('/admin/pages'))->sort(4),
                
                // Camp
                \Filament\Navigation\NavigationItem::make('Summer Camp')->group('Camp')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Iscrizioni')->group('Camp')->url(fn () => url('/admin/pages'))->sort(2),
                
                // Sociale
                \Filament\Navigation\NavigationItem::make('Volley4All')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(1),
                \Filament\Navigation\NavigationItem::make('Progetti Sociali')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(2),
                \Filament\Navigation\NavigationItem::make('Sostenibilità')->group('Sociale')->url(fn () => url('/admin/pages'))->sort(3),
                
                // Media
                \Filament\Navigation\NavigationItem::make('Cartelle Stampa')->group('Media')->url(fn () => url('/admin/pages'))->sort(3),
                \Filament\Navigation\NavigationItem::make('Double Face')->group('Media')->url(fn () => url('/admin/pages'))->sort(4),
                \Filament\Navigation\NavigationItem::make('Foto Gallery')->group('Media')->url(fn () => url('/admin/gallery'))->sort(5),
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
