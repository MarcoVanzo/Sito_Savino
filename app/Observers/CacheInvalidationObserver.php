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
    /**
     * Tutte le chiavi cache utilizzate dal PublicController.
     * Mantenere allineate con le chiavi in PublicController.
     */
    private const PUBLIC_CACHE_KEYS = [
        'public:stagione',
        'public:stagione:b1',
        'public:risultati',
        'public:staff',
        'public:sponsor',
        'public:shop',
    ];

    public function saved(Model $model): void
    {
        $this->clearAllPublicCaches();
    }

    public function deleted(Model $model): void
    {
        $this->clearAllPublicCaches();
    }

    private function clearAllPublicCaches(): void
    {
        foreach (self::PUBLIC_CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }
}
