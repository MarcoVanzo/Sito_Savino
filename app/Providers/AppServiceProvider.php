<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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

        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\StockMovement::observe(\App\Observers\StockMovementObserver::class);
        \App\Models\Roster::observe(\App\Observers\CacheInvalidationObserver::class);
        \App\Models\Player::observe(\App\Observers\CacheInvalidationObserver::class);
        \App\Models\PlayerStat::observe(\App\Observers\CacheInvalidationObserver::class);
        \App\Models\Season::observe(\App\Observers\CacheInvalidationObserver::class);
        \App\Models\Team::observe(\App\Observers\CacheInvalidationObserver::class);

        // Forza HTTPS in produzione
        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
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
