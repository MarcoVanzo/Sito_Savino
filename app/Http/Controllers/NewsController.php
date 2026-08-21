<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    /**
     * Mostra la lista delle notizie pubblicate.
     */
    public function index(): Response
    {
        $page = max(1, min((int) request('page', 1), 100));
        $locale = app()->getLocale();

        $categorySlug = request('categoria') ?: request('category');
        $category = $categorySlug
            ? Category::where('slug', $categorySlug)->firstOrFail()
            : null;

        $categoryKey = $category !== null ? $category->slug : 'all';
        $cacheKey = 'public:news:'.$locale.':cat:'.$categoryKey.':page:'.$page;

        $posts = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($page, $category) {
            $paginator = Post::published()
                ->with(['author', 'categories', 'media'])
                ->when($category, fn ($query) => $query->whereHas(
                    'categories',
                    fn ($q) => $q->where('categories.id', $category->id)
                ))
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(12, ['*'], 'page', $page)
                // Il filtro deve sopravvivere ai link di paginazione.
                ->withQueryString();

            // Trasformiamo ogni post per estrarre la traduzione nella locale corrente prima di cacharlo.
            // ->through() restituisce un NUOVO paginator, non modifica l'originale.
            $transformed = $paginator->through(fn ($post) => $this->postToArray($post));

            return $transformed->toArray();
        });

        return Inertia::render('Public/News', [
            'posts' => $posts,
            'categories' => $this->publishedCategories($locale),
            'activeCategory' => $category?->slug,
        ]);
    }

    /**
     * Categorie da mostrare nei filtri: solo quelle che hanno almeno una
     * notizia pubblicata, altrimenti il lettore trova filtri che portano a
     * una pagina vuota.
     */
    private function publishedCategories(string $locale): array
    {
        return Cache::remember('public:news_categories:'.$locale, now()->addMinutes(30), function () {
            return Category::query()
                ->whereHas('posts', $this->onlyPublished(...))
                ->withCount(['posts' => $this->onlyPublished(...)])
                // L'ordine lo decide la redazione dal pannello: alfabetico
                // metteva "Challenge Cup" prima della stagione in corso.
                // Posizione 0 vuol dire "non assegnata" e va in fondo, non in
                // cima: a parita' resta l'ordine alfabetico.
                ->orderByRaw('sort_order = 0')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $category) => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => $category->posts_count,
                ])
                ->all();
        });
    }

    /**
     * Restringe una query sui post a quelli pubblicati.
     *
     * Metodo separato invece di una closure anonima perché così il tipo della
     * relazione resta noto all'analisi statica.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    private function onlyPublished(Builder $query): Builder
    {
        return $query->published();
    }

    /**
     * Mostra il dettaglio di una singola notizia.
     */
    public function show(string $slug): Response
    {
        $locale = app()->getLocale();

        $data = Cache::remember("public:news:{$locale}:{$slug}", now()->addMinutes(10), function () use ($slug) {
            $post = Post::published()
                ->with(['author', 'categories', 'tags', 'media'])
                ->where('slug', $slug)
                ->firstOrFail();

            $relatedPosts = Post::published()
                ->whereHas('categories', function ($q) use ($post) {
                    $q->whereIn('categories.id', $post->categories->pluck('id'));
                })
                ->where('id', '!=', $post->id)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->take(3)
                ->get()
                ->map(fn ($p) => $this->postToArray($p))
                ->values()
                ->toArray();

            return [
                'post' => $this->postToArray($post),
                'relatedPosts' => $relatedPosts,
            ];
        });

        return Inertia::render('Public/NewsDetail', [
            'post' => $data['post'],
            'relatedPosts' => $data['relatedPosts'],
        ]);
    }

    /**
     * Converte un Post in array risolvendo i campi translatable dalla forma array {"it":"..."}
     * alla stringa nella locale corrente dell'applicazione.
     *
     * Gestisce sia oggetti Eloquent Post che array già serializzati (da cache).
     */
    private function postToArray(Post|array $post): array
    {
        $locale = app()->getLocale();

        if ($post instanceof Post) {
            $translatable = $post->translatable;
            $data = $post->toArray();
        } else {
            // Dati già in forma array (dalla cache)
            $translatable = (new Post)->translatable;
            $data = $post;
        }

        foreach ($translatable as $field) {
            if (! isset($data[$field])) {
                continue;
            }

            if (is_array($data[$field])) {
                // Il campo è un array di traduzioni {"it":"...", "en":"..."} — estrai la locale corrente
                $data[$field] = $data[$field][$locale]
                    ?? $data[$field][array_key_first($data[$field])]
                    ?? '';
            } elseif (is_string($data[$field])) {
                // Se è una stringa JSON (double encoding), decodifica e poi estrai
                $decoded = json_decode($data[$field], true);
                if (is_array($decoded)) {
                    $data[$field] = $decoded[$locale]
                        ?? $decoded[array_key_first($decoded)]
                        ?? $data[$field];
                }
            }
        }

        return $data;
    }
}
