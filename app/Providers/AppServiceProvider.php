<?php

namespace App\Providers;

use App\Models\Game;
use App\Models\Order;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Product;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\User;
use App\Observers\CacheInvalidationObserver;
use App\Observers\OrderObserver;
use App\Observers\StockMovementObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In sviluppo: segnala lazy loading, mass assignment silenzioso,
        // e accesso ad attributi inesistenti
        Model::shouldBeStrict(! app()->isProduction());

        Vite::prefetch(concurrency: 3);

        User::observe(UserObserver::class);
        Order::observe(OrderObserver::class);
        StockMovement::observe(StockMovementObserver::class);
        Roster::observe(CacheInvalidationObserver::class);
        Player::observe(CacheInvalidationObserver::class);
        PlayerStat::observe(CacheInvalidationObserver::class);
        Season::observe(CacheInvalidationObserver::class);
        Team::observe(CacheInvalidationObserver::class);
        Sponsor::observe(CacheInvalidationObserver::class);
        Product::observe(CacheInvalidationObserver::class);
        Post::observe(CacheInvalidationObserver::class);
        Page::observe(CacheInvalidationObserver::class);
        Game::observe(CacheInvalidationObserver::class);

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
