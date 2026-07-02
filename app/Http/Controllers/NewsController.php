<?php

namespace App\Http\Controllers;

use App\Models\Post;
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

        $posts = Cache::remember('public:news:'.$locale.':page:'.$page, now()->addMinutes(5), function () use ($page) {
            $paginator = Post::published()
                ->with(['author', 'categories', 'media'])
                ->orderByDesc('published_at')
                ->paginate(12, ['*'], 'page', $page);

            // Trasformiamo ogni post per estrarre la traduzione nella locale corrente prima di cacharlo.
            // ->through() restituisce un NUOVO paginator, non modifica l'originale.
            $transformed = $paginator->through(fn ($post) => $this->postToArray($post));

            return $transformed->toArray();
        });

        return Inertia::render('Public/News', [
            'posts' => $posts,
        ]);
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

