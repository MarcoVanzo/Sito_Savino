<?php

namespace App\Observers;

use App\Enums\CompetitionType;
use App\Http\Middleware\CachePublicResponse;
use App\Models\Category;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\StaffMember;
use App\Models\Standing;
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
     * Mappa modello → chiavi cache (prefisso, senza suffisso di lingua) da invalidare.
     * Mantenere allineate con le chiavi usate nei controller pubblici: per ogni voce
     * viene invalidata sia la chiave nuda sia la variante "<chiave>:<locale>".
     */
    private const MODEL_CACHE_MAP = [
        Player::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page', 'public:gallery_athletes', 'public:gallery_images', 'public:home', 'filament:dashboard:stats'],
        PlayerStat::class => ['public:stagione', 'public:stagione:b1'],
        Roster::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page'],
        Season::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page', 'public:risultati', 'public:home'],
        Team::class => ['public:stagione', 'public:stagione:b1', 'public:roster_page', 'public:risultati', 'filament:dashboard:next_match'],
        Sponsor::class => ['public:sponsor'],
        Product::class => ['public:shop'],
        ProductCategory::class => ['public:shop'],
        Post::class => ['public:home', 'filament:dashboard:stats'],
        Category::class => ['public:news_categories'],
        Page::class => [],
        Game::class => ['public:risultati', 'public:home', 'filament:dashboard:stats', 'filament:dashboard:next_match'],
        Standing::class => ['public:risultati'],
        StaffMember::class => ['public:staff_tecnico', 'public:staff_medico', 'public:organigramma:page'],
        GalleryEvent::class => ['public:gallery_total_events', 'public:gallery_images'],
        GalleryImage::class => ['public:gallery_images', 'public:gallery_athletes', 'public:gallery_total_events'],
    ];

    /**
     * Locali per cui i controller pubblici scrivono una copia separata della cache.
     *
     * @return array<int, string>
     */
    private function locales(): array
    {
        /** @var array<int, string> $locales */
        $locales = config('app.supported_locales', ['it']);

        return $locales;
    }

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
        $locales = $this->locales();

        foreach ($keys as $key) {
            // Chiave nuda (retrocompatibilità) + una variante per ogni lingua,
            // perché i controller pubblici suffissano sempre la locale.
            Cache::forget($key);

            foreach ($locales as $locale) {
                Cache::forget($key.':'.$locale);
            }
        }

        // I risultati sono cachati per competizione: public:risultati:<competizione>:<locale>
        if (in_array('public:risultati', $keys, true)) {
            foreach (CompetitionType::cases() as $competition) {
                foreach ($locales as $locale) {
                    Cache::forget('public:risultati:'.$competition->value.':'.$locale);
                }
            }
        }

        // La galleria filtrata per atleta usa public:gallery_images:player_<id>:<locale>
        if (in_array('public:gallery_images', $keys, true)) {
            foreach (Player::query()->pluck('id') as $playerId) {
                foreach ($locales as $locale) {
                    Cache::forget('public:gallery_images:player_'.$playerId.':'.$locale);
                }
            }
        }

        // Flush full-page response cache so visitors see fresh content
        $this->flushPageCache();

        // Post: invalida anche la cache per slug e le prime 5 pagine di listing.
        if ($model instanceof Post) {
            $slugs = array_filter([$model->slug, $model->getOriginal('slug')]);

            foreach ($locales as $locale) {
                foreach ($slugs as $slug) {
                    Cache::forget('public:news:'.$locale.':'.$slug);
                }

                Cache::forget('public:news_categories:'.$locale);
            }

            $this->forgetNewsListings($locales);
        }

        // Categoria: cambia l'elenco dei filtri e, se ne cambia lo slug, anche
        // le chiavi delle liste filtrate.
        if ($model instanceof Category) {
            $this->forgetNewsListings($locales);
        }

        // Page: invalida la cache per slug
        if ($model instanceof Page && $model->slug) {
            foreach ($locales as $locale) {
                Cache::forget('public:page:'.$model->slug.':'.$locale);
            }
        }
    }

    /**
     * Svuota le prime 5 pagine del listing news, sia quello completo sia
     * quelli filtrati per categoria.
     *
     * @param  array<int, string>  $locales
     */
    private function forgetNewsListings(array $locales): void
    {
        $categorySlugs = Category::query()->pluck('slug')->push('all');

        foreach ($locales as $locale) {
            foreach ($categorySlugs as $categorySlug) {
                for ($i = 1; $i <= 5; $i++) {
                    Cache::forget('public:news:'.$locale.':cat:'.$categorySlug.':page:'.$i);
                }
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
