<?php

namespace App\Providers;

use App\Filament\Support\TranslatableContentDriver;
use App\Models\Category;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\Order;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerHonour;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\StaffMember;
use App\Models\Standing;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\User;
use App\Observers\CacheInvalidationObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\StockMovementObserver;
use App\Observers\UserObserver;
use App\Services\Analytics\WebAnalyticsService;
use App\Services\Social\SocialAnalyticsService;
use App\Services\Wikipedia\WikipediaClient;
use Filament\SpatieLaravelTranslatableContentDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Il plugin translatable istanzia il proprio content driver dal container
        // (`app($driver, ['activeLocale' => …])`) e ne ha il nome cablato nel trait
        // HasActiveLocaleSwitcher: rimpiazzarlo qui è l'unico punto d'aggancio che
        // non richieda di sovrascrivere ogni pagina di ogni risorsa.
        $this->app->bind(SpatieLaravelTranslatableContentDriver::class, TranslatableContentDriver::class);

        // I servizi di analytics tengono una memoria interna per richiesta: la
        // pagina e i suoi widget sono componenti Livewire distinti che chiedono
        // gli stessi identici numeri, e senza istanza condivisa ognuno
        // ripasserebbe da cache e serializzazione per lo stesso payload.
        // Il client GA4 non è autowirable (le credenziali si costruiscono da una
        // factory, non dal container): il servizio se lo crea da sé quando serve.
        $this->app->singleton(WebAnalyticsService::class, static fn (): WebAnalyticsService => new WebAnalyticsService);
        $this->app->singleton(SocialAnalyticsService::class);

        // Il client Wikipedia si costruisce dalla configurazione (lingua,
        // timeout, user agent): non è autowirable perché i parametri sono
        // scalari.
        $this->app->bind(WikipediaClient::class, static fn (): WikipediaClient => WikipediaClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In sviluppo: segnala lazy loading, mass assignment silenzioso,
        // e accesso ad attributi inesistenti
        Model::shouldBeStrict(! app()->isProduction());

        // Requisiti minimi di robustezza, applicati ovunque si usi
        // Rules\Password::defaults(). `uncompromised()` interroga l'API di
        // HaveIBeenPwned (k-anonymity: viene inviato solo il prefisso dell'hash,
        // mai la password): la attiviamo solo in produzione per non rendere
        // i test dipendenti dalla rete.
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Bypass globale per il super admin: senza questo, ogni nuova policy deve
        // ricordarsi di reinserire il controllo a mano. DEVE restituire null (non
        // false) per gli altri ruoli, altrimenti il gate corto-circuita in negativo
        // e nessun'altra policy verrebbe mai consultata.
        Gate::before(function (User $user): ?bool {
            return $user->role->isSuperAdmin() ? true : null;
        });

        User::observe(UserObserver::class);
        Order::observe(OrderObserver::class);
        StockMovement::observe(StockMovementObserver::class);
        Roster::observe(CacheInvalidationObserver::class);
        Player::observe(CacheInvalidationObserver::class);
        PlayerStat::observe(CacheInvalidationObserver::class);
        PlayerHonour::observe(CacheInvalidationObserver::class);
        Season::observe(CacheInvalidationObserver::class);
        Team::observe(CacheInvalidationObserver::class);
        Sponsor::observe(CacheInvalidationObserver::class);
        Standing::observe(CacheInvalidationObserver::class);
        Product::observe(CacheInvalidationObserver::class);
        Product::observe(ProductObserver::class);
        ProductCategory::observe(CacheInvalidationObserver::class);
        Post::observe(CacheInvalidationObserver::class);
        Category::observe(CacheInvalidationObserver::class);
        Page::observe(CacheInvalidationObserver::class);
        Game::observe(CacheInvalidationObserver::class);
        StaffMember::observe(CacheInvalidationObserver::class);
        GalleryEvent::observe(CacheInvalidationObserver::class);
        GalleryImage::observe(CacheInvalidationObserver::class);

        // Forza HTTPS in produzione
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Rate limiters
        RateLimiter::for('web', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
