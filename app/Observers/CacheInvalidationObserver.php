<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida le cache pubbliche quando i dati correlati cambiano.
 * Registrato in AppServiceProvider per: Roster, Player, PlayerStat, Season, Team.
 */
class CacheInvalidationObserver
{
    public function saved(Model $model): void
    {
        Cache::forget('public:stagione');
    }

    public function deleted(Model $model): void
    {
        Cache::forget('public:stagione');
    }
}
