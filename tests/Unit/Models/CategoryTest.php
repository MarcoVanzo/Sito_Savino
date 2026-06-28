<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_many_posts(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create();
        $category->posts()->attach($post);

        $this->assertCount(1, $category->posts);
    }

    public function test_category_can_have_parent(): void
    {
        $parent = Category::factory()->create(['name' => 'Sport']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Volley']);

        $this->assertInstanceOf(Category::class, $child->parent);
        $this->assertEquals('Sport', $child->parent->name);
    }

    public function test_category_can_have_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->count(3)->create(['parent_id' => $parent->id]);

        $this->assertCount(3, $parent->children);
    }

    public function test_root_category_has_null_parent(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);

        $this->assertNull($root->parent);
    }
}
