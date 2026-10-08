<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    #[Test]
    public function shop_index_returns_200(): void
    {
        $response = $this->get(route('shop'));
        $response->assertStatus(200);
    }

    #[Test]
    public function shop_search_returns_200(): void
    {
        $response = $this->get(route('shop.search', ['q' => 'test']));
        $response->assertStatus(200);
    }

    #[Test]
    public function shop_category_returns_200(): void
    {
        $category = ProductCategory::factory()->create();

        $response = $this->get(route('shop.category', $category));
        $response->assertStatus(200);
    }

    #[Test]
    public function shop_product_returns_200(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
        ]);

        $response = $this->get(route('shop.product', $product));
        $response->assertStatus(200);
    }

    #[Test]
    public function shop_product_returns_404_for_inactive(): void
    {
        $product = Product::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->get(route('shop.product', $product));
        $response->assertStatus(404);
    }

    #[Test]
    public function shop_size_guide_returns_200(): void
    {
        $response = $this->get(route('shop.size-guide'));
        $response->assertStatus(200);
    }
}
