<?php

namespace App\Observers;

use App\Enums\CompetitionType;
use App\Http\Middleware\CachePublicResponse;
use App\Jobs\RicostruisciLaCacheDellaGallery;
use App\Models\Category;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\MenuItem;
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
use App\Models\Team;
use App\Services\GalleryArchive;
use App\Services\NewsFeedBuilder;
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
    /**
     * Le pagine con una rosa: la prima squadra e una per ogni categoria del
     * vivaio (`Team::CATEGORIE_VIVAIO`). Stavano scritte a mano voce per voce e
     * si erano fermate alla B1: aperte /stagione/u17 e /stagione/u15, una rosa
     * modificata in redazione continuava a mostrare quella vecchia finché la
     * cache non scadeva da sé. `CacheStagioneVivaioTest` tiene allineati i due
     * elenchi.
     *
     * @var list<string>
     */
    private const CHIAVI_STAGIONE = [
        'public:stagione',
        'public:stagione:b1',
        'public:stagione:u17',
        'public:stagione:u15',
    ];

    private const MODEL_CACHE_MAP = [
        Player::class => [...self::CHIAVI_STAGIONE, 'public:roster_page', 'public:gallery_athletes', 'public:gallery_images', 'public:home', 'filament:dashboard:stats'],
        PlayerStat::class => self::CHIAVI_STAGIONE,
        PlayerHonour::class => self::CHIAVI_STAGIONE,
        Roster::class => [...self::CHIAVI_STAGIONE, 'public:roster_page'],
        Season::class => [...self::CHIAVI_STAGIONE, 'public:roster_page', 'public:risultati', 'public:home'],
        Team::class => [...self::CHIAVI_STAGIONE, 'public:roster_page', 'public:risultati', 'filament:dashboard:next_match_id'],
        Sponsor::class => ['public:sponsor', 'public:sponsor:tiers'],
        Product::class => ['public:shop'],
        ProductCategory::class => ['public:shop'],
        Post::class => ['public:home', 'filament:dashboard:stats'],
        Category::class => ['public:news_categories'],
        Page::class => [],
        Game::class => ['public:risultati', 'public:home', 'filament:dashboard:stats', 'filament:dashboard:next_match_id'],
        // Gli slide sono il primo schermo della homepage e si cambiano spesso:
        // senza questa voce restavano quelli vecchi per i cinque minuti di
        // `public:home`, e la redazione ricaricava senza vedere niente.
        HeroSlide::class => ['public:home'],
        Standing::class => ['public:risultati'],
        StaffMember::class => ['public:staff_tecnico', 'public:staff_medico', 'public:organigramma:page'],
        GalleryEvent::class => ['public:gallery_images'],
        GalleryImage::class => ['public:gallery_images', 'public:gallery_athletes'],
    ];

    /**
     * Locali per cui i controller pubblici scrivono una copia separata della cache.
     *
     * @return array<int, string>
     */
    private function locales(): array
    {
        /** @var array<int, string> */
        return config('app.supported_locales', ['it']);
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
        $keys = self::MODEL_CACHE_MAP[get_class($model)] ?? [];
        $locales = $this->locales();

        foreach ($keys as $key) {
            // L'archivio completo della gallery non si butta: ricostruirlo
            // costa una decina di secondi che pagherebbe il primo visitatore.
            // Si rigenera in coda, e intanto resta servita la copia di prima.
            if ($key === GalleryArchive::CHIAVE) {
                RicostruisciLaCacheDellaGallery::dispatch()->afterCommit();

                continue;
            }

            // Chiave nuda (retrocompatibilità) + una variante per ogni lingua,
            // perché i controller pubblici suffissano sempre la locale.
            Cache::forget($key);

            foreach ($locales as $locale) {
                Cache::forget($key.':'.$locale);
            }
        }

        $this->dimenticaLeChiaviComposte($keys, $locales);

        // Flush full-page response cache so visitors see fresh content
        $this->flushPageCache();

        $this->dimenticaLeChiaviDelModello($model, $locales);
    }

    /**
     * Le chiavi che non sono una sola: i risultati sono per competizione e la
     * galleria ha una variante per ogni atleta.
     *
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $locales
     */
    private function dimenticaLeChiaviComposte(array $keys, array $locales): void
    {
        // public:risultati:<competizione>:<locale>
        if (in_array('public:risultati', $keys, true)) {
            foreach (CompetitionType::cases() as $competition) {
                foreach ($locales as $locale) {
                    Cache::forget('public:risultati:'.$competition->value.':'.$locale);
                }
            }
        }

        // public:gallery_images:player_<id>:<locale>
        if (in_array(GalleryArchive::CHIAVE, $keys, true)) {
            foreach (Player::query()->pluck('id') as $playerId) {
                foreach ($locales as $locale) {
                    Cache::forget(GalleryArchive::CHIAVE.':player_'.$playerId.':'.$locale);
                }
            }
        }
    }

    /**
     * Le chiavi che dipendono dal contenuto della riga toccata: lo slug di una
     * news o di una pagina, l'elenco delle categorie.
     *
     * @param  array<int, string>  $locales
     */
    private function dimenticaLeChiaviDelModello(Model $model, array $locales): void
    {
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
            $this->dimenticaIlFeedDelleNotizie($locales);
        }

        // Categoria: cambia l'elenco dei filtri e, se ne cambia lo slug, anche
        // le chiavi delle liste filtrate.
        if ($model instanceof Category) {
            $this->forgetNewsListings($locales);
            $this->dimenticaIlFeedDelleNotizie($locales);
        }

        // Page: invalida la cache per slug
        if ($model instanceof Page && $model->slug) {
            foreach ($locales as $locale) {
                Cache::forget('public:page:'.$model->slug.':'.$locale);
            }

            // Il menu nasconde le voci che portano a una pagina non
            // pubblicata: mettendone una in bozza, la voce deve sparire
            // subito. Senza questo restava fino alla scadenza della cache e
            // continuava a portare a "pagina non trovata".
            MenuItem::clearCache();
        }
    }

    /**
     * Il feed RSS lo rileggono gli aggregatori, a partire da quello della
     * Lega: una notizia corretta o ritirata deve sparirne subito, non entro
     * la mezz'ora di cache.
     *
     * @param  array<int, string>  $locales
     */
    private function dimenticaIlFeedDelleNotizie(array $locales): void
    {
        foreach ($locales as $locale) {
            Cache::forget(NewsFeedBuilder::chiaveDiCache($locale));
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
