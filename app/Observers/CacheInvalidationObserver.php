<?php

namespace App\Observers;

use App\Enums\CompetitionType;
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
     * Locali per cui i controller pubblici scrivono una copia separata della cache.
     */
    private const LOCALES = ['it', 'en'];

    /**
     * Mappa modello → chiavi cache (prefisso, senza suffisso di lingua) da invalidare.
     * Mantenere allineate con le chiavi usate nei controller pubblici: per ogni voce
     * viene invalidata sia la chiave nuda sia la variante "<chiave>:<locale>".
     */
    private const MODEL_CACHE_MAP = [
        Player::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page', 'public:gallery_athletes', 'public:home', 'filament:dashboard:stats'],
        PlayerStat::class => ['public:stagione', 'public:stagione:b1'],
        Roster::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page'],
        Season::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page', 'public:risultati', 'public:home'],
        Team::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page', 'public:risultati', 'filament:dashboard:next_match'],
        Sponsor::class => ['public:sponsor'],
        Product::class => ['public:shop'],
        Post::class => ['public:home', 'filament:dashboard:stats'],
        Page::class => [],
        Game::class => ['public:risultati', 'public:home', 'filament:dashboard:stats', 'filament:dashboard:next_match'],
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
            // Chiave nuda (retrocompatibilità) + una variante per ogni lingua,
            // perché i controller pubblici suffissano sempre la locale.
            Cache::forget($key);

            foreach (self::LOCALES as $locale) {
                Cache::forget($key.':'.$locale);
            }
        }

        // I risultati sono cachati per competizione: public:risultati:<competizione>:<locale>
        if (in_array('public:risultati', $keys, true)) {
            foreach (CompetitionType::cases() as $competition) {
                foreach (self::LOCALES as $locale) {
                    Cache::forget('public:risultati:'.$competition->value.':'.$locale);
                }
            }
        }

        // Flush full-page response cache so visitors see fresh content
        $this->flushPageCache();

        // Post: invalida anche la cache per slug e le prime 5 pagine di listing
        if ($model instanceof Post) {
            $slugs = array_filter([$model->slug, $model->getOriginal('slug')]);

            foreach (self::LOCALES as $locale) {
                foreach ($slugs as $slug) {
                    Cache::forget('public:news:'.$locale.':'.$slug);
                }
                for ($i = 1; $i <= 5; $i++) {
                    Cache::forget('public:news:'.$locale.':page:'.$i);
                }
            }
        }

        // Page: invalida la cache per slug
        if ($model instanceof Page && $model->slug) {
            foreach (self::LOCALES as $locale) {
                Cache::forget('public:page:'.$model->slug.':'.$locale);
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
