<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    /**
     * Mostra la lista delle notizie pubblicate.
     */
    public function index(): Response
    {
        $posts = Post::published()
            ->with(['author', 'categories', 'media'])
            ->orderByDesc('published_at')
            ->paginate(12);

        return Inertia::render('Public/News', [
            'posts' => $posts,
        ]);
    }

    /**
     * Mostra il dettaglio di una singola notizia.
     */
    public function show(string $slug): Response
    {
        $post = Post::published()
            ->with(['author', 'categories', 'tags', 'media'])
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        return Inertia::render('Public/NewsDetail', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }
}
