<?php

namespace Tests\Unit\Models;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_has_published_scope(): void
    {
        Post::factory()->create(['status' => PostStatus::Published]);
        Post::factory()->create(['status' => PostStatus::Draft]);

        $this->assertCount(1, Post::published()->get());
    }

    public function test_post_belongs_to_author(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertEquals($user->id, $post->author->id);
    }

    public function test_post_has_many_categories(): void
    {
        $post = Post::factory()->create();
        $category = Category::factory()->create();
        $post->categories()->attach($category);

        $this->assertCount(1, $post->categories);
        $this->assertInstanceOf(Category::class, $post->categories->first());
    }

    public function test_post_has_many_tags(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();
        $post->tags()->attach($tag);

        $this->assertCount(1, $post->tags);
        $this->assertInstanceOf(Tag::class, $post->tags->first());
    }

    public function test_published_at_is_cast_to_datetime(): void
    {
        $post = Post::factory()->create(['published_at' => '2025-01-15 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $post->published_at);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Published]);

        $this->assertInstanceOf(PostStatus::class, $post->status);
        $this->assertEquals(PostStatus::Published, $post->status);
    }
}
