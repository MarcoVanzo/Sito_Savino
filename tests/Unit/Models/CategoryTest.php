<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function category_has_many_posts(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create();
        $category->posts()->attach($post);

        $this->assertCount(1, $category->posts);
    }

    #[Test]
    public function category_can_have_parent(): void
    {
        $parent = Category::factory()->create(['name' => 'Sport']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Volley']);

        $this->assertInstanceOf(Category::class, $child->parent);
        $this->assertEquals('Sport', $child->parent->name);
    }

    #[Test]
    public function category_can_have_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->count(3)->create(['parent_id' => $parent->id]);

        $this->assertCount(3, $parent->children);
    }

    #[Test]
    public function root_category_has_null_parent(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);

        $this->assertNull($root->parent);
    }
}
