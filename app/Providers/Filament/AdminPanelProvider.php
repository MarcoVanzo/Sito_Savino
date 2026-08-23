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

        // L'elenco delle voci sta in database/data/, con gli altri contenuti
        // iniziali: sono dati, e qui dentro erano settanta righe di array che
        // il rilevatore di cloni segnalava come codice duplicato.
        $voci = require database_path('data/voci_di_menu_in_costruzione.php');

        $slugMap = [];

        foreach ($voci as $voce) {
            if ($voce['slug'] !== null) {
                $slugMap[$voce['etichetta']] = $voce['slug'];
            }
        }

        $navigationItems = [];

        foreach ($voci as $voce) {
            $navigationItems[] = NavigationItem::make($voce['etichetta'])
                ->group($voce['gruppo'])
                ->url(self::wipItemUrl($voce['etichetta'], $slugMap, $underConstructionUrl))
                ->sort($voce['ordine']);
        }

        return $navigationItems;
    }

    /**
     * Indirizzo della voce: la pagina del CMS se esiste, altrimenti la pagina
     * "in costruzione". Risolto alla prima chiamata e non alla costruzione del
     * menu, perche' il pannello si carica anche a database non migrato.
     *
     * @param  array<string, string>  $slugMap
     */
    private static function wipItemUrl(string $label, array $slugMap, string $underConstructionUrl): \Closure
    {
        return function () use ($label, $slugMap, $underConstructionUrl) {
            try {
                $slug = $slugMap[$label] ?? null;
                $page = $slug ? Page::where('slug', $slug)->first() : null;

                if ($page) {
                    return "/admin/pages/{$page->id}/edit";
                }
            } catch (\Throwable) {
                // Fallback: database non raggiungibile o non ancora migrato.
            }

            return $underConstructionUrl;
        };
    }
}
