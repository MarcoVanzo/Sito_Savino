<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_news_index_returns_200(): void
    {
        $response = $this->get('/news');
        $response->assertStatus(200);
    }

    public function test_news_index_shows_only_published_posts(): void
    {
        Post::factory()->create(['status' => PostStatus::Published, 'title' => 'Post Pubblicato']);
        Post::factory()->create(['status' => PostStatus::Draft, 'title' => 'Post Bozza']);

        $response = $this->get('/news');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Public/News')
            ->has('posts.data', 1)
        );
    }

    public function test_news_show_returns_200_for_published_post(): void
    {
        Post::factory()->create([
            'status' => PostStatus::Published,
            'slug' => 'test-news',
        ]);

        $response = $this->get('/news/test-news');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Public/NewsDetail')
            ->has('post')
            ->where('post.slug', 'test-news')
        );
    }

    public function test_news_show_returns_404_for_draft_post(): void
    {
        Post::factory()->create([
            'status' => PostStatus::Draft,
            'slug' => 'draft-news',
        ]);

        $response = $this->get('/news/draft-news');
        $response->assertStatus(404);
    }

    public function test_news_show_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->get('/news/this-does-not-exist');
        $response->assertStatus(404);
    }

    public function test_news_index_filters_by_category(): void
    {
        $comunicati = Category::factory()->create(['slug' => 'comunicati', 'name' => 'Comunicati']);
        $mercato = Category::factory()->create(['slug' => 'mercato', 'name' => 'Mercato']);

        Post::factory()->create(['status' => PostStatus::Published, 'title' => 'Comunicato'])
            ->categories()->attach($comunicati);
        Post::factory()->create(['status' => PostStatus::Published, 'title' => 'Trasferimento'])
            ->categories()->attach($mercato);

        $this->get('/news?categoria=comunicati')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/News')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Comunicato')
                ->where('activeCategory', 'comunicati')
            );
    }

    public function test_news_index_only_offers_categories_that_have_published_posts(): void
    {
        $conNotizie = Category::factory()->create(['slug' => 'comunicati', 'name' => 'Comunicati']);
        $soloBozze = Category::factory()->create(['slug' => 'vuota', 'name' => 'Vuota']);

        Post::factory()->create(['status' => PostStatus::Published])->categories()->attach($conNotizie);
        Post::factory()->create(['status' => PostStatus::Draft])->categories()->attach($soloBozze);

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('categories', 1)
                ->where('categories.0.slug', 'comunicati')
                ->where('categories.0.count', 1)
            );
    }

    public function test_news_index_returns_404_for_unknown_category(): void
    {
        $this->get('/news?categoria=categoria-inesistente')->assertNotFound();
    }

    public function test_english_news_index_accepts_the_english_query_parameter(): void
    {
        $category = Category::factory()->create(['slug' => 'press', 'name' => 'Press']);
        Post::factory()->create(['status' => PostStatus::Published])->categories()->attach($category);
        Post::factory()->create(['status' => PostStatus::Published]);

        $this->get('/en/news?category=press')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts.data', 1)
                ->where('activeCategory', 'press')
            );
    }

    public function test_category_filter_does_not_leak_across_cached_pages(): void
    {
        $comunicati = Category::factory()->create(['slug' => 'comunicati', 'name' => 'Comunicati']);

        Post::factory()->create(['status' => PostStatus::Published, 'title' => 'Comunicato'])
            ->categories()->attach($comunicati);
        Post::factory()->create(['status' => PostStatus::Published, 'title' => 'Senza categoria']);

        // La lista completa viene messa in cache per prima: la chiave della
        // lista filtrata deve restare distinta, altrimenti il filtro
        // restituisce i risultati già cachati di "tutte".
        $this->get('/news')->assertInertia(fn ($page) => $page->has('posts.data', 2));

        $this->get('/news?categoria=comunicati')
            ->assertInertia(fn ($page) => $page->has('posts.data', 1));
    }

    public function test_news_show_includes_related_posts(): void
    {
        $category = Category::factory()->create();

        $post = Post::factory()->create(['status' => PostStatus::Published, 'slug' => 'main-post']);
        $post->categories()->attach($category);

        $related = Post::factory()->count(5)->create(['status' => PostStatus::Published]);
        $related->each(fn ($p) => $p->categories()->attach($category));

        $response = $this->get('/news/main-post');
        $response->assertInertia(fn ($page) => $page->has('relatedPosts')
            ->where('relatedPosts', fn ($relatedPosts) => count($relatedPosts) > 0 && count($relatedPosts) <= 5
            )
        );
    }
}
