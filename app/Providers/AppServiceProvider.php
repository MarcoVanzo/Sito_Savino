<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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

        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\StockMovement::observe(\App\Observers\StockMovementObserver::class);

        // Forza HTTPS in produzione
        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
