<?php

namespace App\Observers;

use App\Http\Middleware\CachePublicResponse;

use App\Models\Game;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Product;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida le cache pubbliche quando i dati correlati cambiano.
 * Registrato in AppServiceProvider per tutti i modelli pubblici.
 */
class CacheInvalidationObserver
{
    /**
     * Mappa modello → chiavi cache da invalidare.
     * Mantenere allineate con le chiavi usate nei controller pubblici.
     */
    private const MODEL_CACHE_MAP = [
        Player::class => ['public:stagione', 'public:stagione:b1', 'public:staff', 'public:home'],
        PlayerStat::class => ['public:stagione', 'public:stagione:b1'],
        Roster::class => ['public:stagione', 'public:stagione:b1'],
        Season::class => ['public:stagione', 'public:stagione:b1', 'public:risultati', 'public:home'],
        Team::class => ['public:stagione', 'public:stagione:b1', 'public:risultati'],
        Sponsor::class => ['public:sponsor'],
        Product::class => ['public:shop'],
        Post::class => ['public:home'],
        Page::class => [],
        Game::class => ['public:risultati', 'public:home'],
    ];

    public function saved(Model $model): void
    {
        $this->clearCachesForModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->clearCachesForModel($model);
    }

    private function clearCachesForModel(Model $model): void
    {
        $class = get_class($model);
        $keys = self::MODEL_CACHE_MAP[$class] ?? [];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Flush full-page response cache so visitors see fresh content
        $this->flushPageCache();

        // Post: invalida anche la cache per slug e le prime 5 pagine di listing
        if ($model instanceof Post) {
            if ($model->slug) {
                Cache::forget('public:news:'.$model->slug);
            }
            for ($i = 1; $i <= 5; $i++) {
                Cache::forget('public:news:page:'.$i);
            }
        }

        // Page: invalida la cache per slug
        if ($model instanceof Page) {
            if ($model->slug) {
                Cache::forget('public:page:'.$model->slug);
            }
        }
    }

    /**
     * Flush only full-page response cache entries.
     * Uses CachePublicResponse's registry-based flush to avoid
     * clearing controller-level caches unnecessarily.
     */
    private function flushPageCache(): void
    {
        CachePublicResponse::flush();
    }
}
