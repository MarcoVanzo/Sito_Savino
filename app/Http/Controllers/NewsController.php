<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
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
        $page = request('page', 1);

        $posts = Cache::remember('public:news:page:' . $page, now()->addMinutes(5), function () {
            return Post::published()
                ->with(['author', 'categories', 'media'])
                ->orderByDesc('published_at')
                ->paginate(12);
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
        $data = Cache::remember('public:news:' . $slug, now()->addMinutes(10), function () use ($slug) {
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
                ->get();

            return compact('post', 'relatedPosts');
        });

        return Inertia::render('Public/NewsDetail', [
            'post' => $data['post'],
            'relatedPosts' => $data['relatedPosts'],
        ]);
    }
}
