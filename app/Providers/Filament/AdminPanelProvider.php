<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
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
            ->spa()
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
                NavigationGroup::make()->label('Marketing')->icon('heroicon-o-envelope'),
                NavigationGroup::make()->label('Pagine & Extra')->icon('heroicon-o-document-duplicate')->collapsed(),
                NavigationGroup::make()->label('Amministrazione')->icon('heroicon-o-cog-8-tooth')->collapsed(),
            ])
            ->navigationItems(self::wipNavigationItems())
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
            ->databaseNotificationsPolling('120s')
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

    /**
     * Voci di navigazione "in costruzione".
     * L'URL viene risolto una sola volta e riusato per tutte le voci,
     * invece di chiamare UnderConstruction::getUrl() 25 volte per render.
     */
    private static function wipNavigationItems(): array
    {
        $url = '/admin/under-construction';

        $items = [
            // Stagione
            ['CEV Champions League', 'Stagione', 4],
            ['Coppa Italia & Playoff', 'Stagione', 5],
            // Società
            ['Storia', 'Società', 2],
            ['Safeguarding', 'Società', 3],
            ['Contatti', 'Società', 4],
            ['Palazzetto & Google Maps', 'Società', 5],
            // Ticketing
            ['Biglietteria', 'Ticketing', 1],
            ['Campagna Abbonamenti', 'Ticketing', 2],
            ['Accessibilità & Info', 'Ticketing', 3],
            ['Convenzioni', 'Ticketing', 4],
            // Sponsor
            ['Diventa Sponsor', 'Sponsor', 2],
            ['Title Sponsor (SDB)', 'Sponsor', 3],
            ['Hospitality', 'Sponsor', 4],
            // SDB Youth
            ['Settore Giovanile', 'SDB Youth', 3],
            ['Talent Day & Recruiting', 'SDB Youth', 4],
            ['Progetto Affiliazioni', 'SDB Youth', 5],
            // Summer Camp
            ['Tutte le Info', 'Summer Camp', 1],
            ['Iscrizione (Experience)', 'Summer Camp', 2],
            // Sociale
            ['Progetti Sociali', 'Sociale', 1],
            ['Volley 4 All', 'Sociale', 2],
            ['Bilancio Sostenibilità', 'Sociale', 3],
            ['Progetto Scuola', 'Sociale', 4],
            // Comunicazione
            ['Cartelle Stampa', 'Comunicazione', 2],
            ['Magazine', 'Comunicazione', 3],
            ['Double Face', 'Comunicazione', 4],

        ];

        return array_map(
            fn (array $item) => NavigationItem::make($item[0])
                ->group($item[1])
                ->url($url)
                ->sort($item[2]),
            $items
        );
    }
}
