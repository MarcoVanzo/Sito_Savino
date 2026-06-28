<?php
namespace Tests\Feature;

use App\Enums\PostStatus;
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
        $response->assertInertia(fn ($page) =>
            $page->component('Public/News')
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
        $response->assertInertia(fn ($page) =>
            $page->component('Public/NewsDetail')
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

    public function test_news_show_includes_related_posts(): void
    {
        $category = \App\Models\Category::factory()->create();

        $post = Post::factory()->create(['status' => PostStatus::Published, 'slug' => 'main-post']);
        $post->categories()->attach($category);

        $related = Post::factory()->count(5)->create(['status' => PostStatus::Published]);
        $related->each(fn ($p) => $p->categories()->attach($category));

        $response = $this->get('/news/main-post');
        $response->assertInertia(fn ($page) =>
            $page->has('relatedPosts')
                ->where('relatedPosts', fn ($relatedPosts) =>
                    count($relatedPosts) > 0 && count($relatedPosts) <= 5
                )
        );
    }
}
