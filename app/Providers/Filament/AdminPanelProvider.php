<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\Page;
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
            // Lo staff accede al CMS dalla login nativa di Filament (/admin/login),
            // brandizzata dai colori e dal logo impostati qui sotto. Il reset
            // password dello staff segue lo stesso percorso (/admin/password-reset).
            ->login()
            ->passwordReset(ResetPassword::class)
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
                NavigationGroup::make()->label('Stagione')->icon('heroicon-o-trophy')->collapsed(),
                NavigationGroup::make()->label('Società')->icon('heroicon-o-building-office-2')->collapsed(),
                NavigationGroup::make()->label('Ticketing')->icon('heroicon-o-ticket')->collapsed(),
                NavigationGroup::make()->label('Sponsor')->icon('heroicon-o-currency-dollar')->collapsed(),
                NavigationGroup::make()->label('SDB Youth')->icon('heroicon-o-academic-cap')->collapsed(),
                NavigationGroup::make()->label('Summer Camp')->icon('heroicon-o-sun')->collapsed(),
                NavigationGroup::make()->label('Sociale')->icon('heroicon-o-heart')->collapsed(),
                NavigationGroup::make()->label('Comunicazione')->icon('heroicon-o-megaphone')->collapsed(),
                NavigationGroup::make()->label('Shop Ufficiale')->icon('heroicon-o-shopping-bag')->collapsed(),
                NavigationGroup::make()->label('Marketing')->icon('heroicon-o-envelope')->collapsed(),
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
                    ->defaultLocales(config('app.supported_locales'))
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('filament.hooks.footer')->render()
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_BEFORE,
                fn (): string => view('filament.hooks.password-expiry-banner')->render()
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.hooks.sidebar-accordion')->render()
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
                EnsurePasswordIsChanged::class,
            ]);
    }

    /**
     * Voci di navigazione "in costruzione".
     * L'URL viene risolto una sola volta e riusato per tutte le voci,
     * invece di chiamare UnderConstruction::getUrl() 25 volte per render.
     */
    private static function wipNavigationItems(): array
    {
        $underConstructionUrl = '/admin/under-construction';

        $slugMap = [
            'Organigramma' => 'organigramma',
            'Storia' => 'storia',
            'Safeguarding' => 'safeguarding',
            'Contatti' => 'contatti',
            'Palazzetto & Google Maps' => 'palazzetto',
            'Biglietteria' => 'biglietteria',
            'Campagna Abbonamenti' => 'abbonamenti',
            'Accessibilità & Info' => 'accessibilita',
            'Convenzioni' => 'convenzioni',
            'Diventa Sponsor' => 'diventa-sponsor',
            'Title Sponsor (SDB)' => 'title-sponsor',
            'Hospitality' => 'hospitality',
            'Settore Giovanile' => 'settore-giovanile',
            'Talent Day & Recruiting' => 'talent-day',
            'Progetto Affiliazioni' => 'affiliazioni',
            'Tutte le Info' => 'summer-camp',
            'Iscrizione (Experience)' => 'iscrizione-experience',
            'Progetti Sociali' => 'progetti-sociali',
            'Volley 4 All' => 'volley-4-all',
            'Bilancio Sostenibilità' => 'sostenibilita',
            'Progetto Scuola' => 'progetto-scuola',
            'Cartelle Stampa' => 'cartelle-stampa',
            'Magazine' => 'magazine',
            'Double Face' => 'double-face',
            'Accrediti Stampa' => 'accrediti-stampa',
        ];

        $items = [
            // Stagione
            ['CEV Champions League', 'Stagione', 4],
            ['Coppa Italia & Playoff', 'Stagione', 5],
            // Società
            ['Organigramma', 'Società', 1],
            ['Storia', 'Società', 2],
            ['Safeguarding', 'Società', 3],
            ['Contatti', 'Società', 4],
            ['Palazzetto & Google Maps', 'Società', 6],
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
            ['Accrediti Stampa', 'Comunicazione', 1],
            ['Cartelle Stampa', 'Comunicazione', 2],
            ['Magazine', 'Comunicazione', 3],
            ['Double Face', 'Comunicazione', 4],
        ];

        return array_map(
            function (array $item) use ($slugMap, $underConstructionUrl) {
                $label = $item[0];
                $group = $item[1];
                $sort = $item[2];

                $urlClosure = function () use ($label, $slugMap, $underConstructionUrl) {
                    try {
                        if (isset($slugMap[$label])) {
                            $slug = $slugMap[$label];
                            $page = Page::where('slug', $slug)->first();
                            if ($page) {
                                return "/admin/pages/{$page->id}/edit";
                            }
                        }
                    } catch (\Throwable $e) {
                        // Safe fallback in case database is not accessible/migrated yet
                    }

                    return $underConstructionUrl;
                };

                return NavigationItem::make($label)
                    ->group($group)
                    ->url($urlClosure)
                    ->sort($sort);
            },
            $items
        );
    }
}
