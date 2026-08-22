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

        // Le voci sono raggruppate per gruppo di navigazione: il nome del gruppo
        // compare una volta sola, come chiave, e non piu' ripetuto riga per riga.
        $itemsByGroup = [
            'Stagione' => [
                ['CEV Champions League', 4],
                ['Coppa Italia & Playoff', 5],
            ],
            'Società' => [
                ['Organigramma', 1],
                ['Storia', 2],
                ['Safeguarding', 3],
                ['Contatti', 4],
                ['Palazzetto & Google Maps', 6],
            ],
            'Ticketing' => [
                ['Biglietteria', 1],
                ['Campagna Abbonamenti', 2],
                ['Accessibilità & Info', 3],
                ['Convenzioni', 4],
            ],
            'Sponsor' => [
                ['Diventa Sponsor', 2],
                ['Title Sponsor (SDB)', 3],
                ['Hospitality', 4],
            ],
            'SDB Youth' => [
                ['Settore Giovanile', 3],
                ['Talent Day & Recruiting', 4],
                ['Progetto Affiliazioni', 5],
            ],
            'Summer Camp' => [
                ['Tutte le Info', 1],
                ['Iscrizione (Experience)', 2],
            ],
            'Sociale' => [
                ['Progetti Sociali', 1],
                ['Volley 4 All', 2],
                ['Bilancio Sostenibilità', 3],
                ['Progetto Scuola', 4],
            ],
            'Comunicazione' => [
                ['Accrediti Stampa', 1],
                ['Cartelle Stampa', 2],
                ['Magazine', 3],
                ['Double Face', 4],
            ],
        ];

        $navigationItems = [];

        foreach ($itemsByGroup as $group => $groupItems) {
            foreach ($groupItems as [$label, $sort]) {
                $navigationItems[] = NavigationItem::make($label)
                    ->group($group)
                    ->url(self::wipItemUrl($label, $slugMap, $underConstructionUrl))
                    ->sort($sort);
            }
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
