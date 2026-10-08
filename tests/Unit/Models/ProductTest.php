<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_belongs_to_category(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['product_category_id' => $category->id]);

        $this->assertInstanceOf(ProductCategory::class, $product->category);
        $this->assertEquals($category->id, $product->category->id);
    }

    #[Test]
    public function product_has_many_variants(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->assertCount(1, $product->variants);
        $this->assertInstanceOf(ProductVariant::class, $product->variants->first());
    }

    #[Test]
    public function product_has_many_stock_movements(): void
    {
        $product = Product::factory()->create();
        StockMovement::factory()->create(['product_id' => $product->id]);

        $this->assertCount(1, $product->stockMovements);
    }

    #[Test]
    public function product_active_scope(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->assertCount(1, Product::active()->get());
    }

    #[Test]
    public function product_uses_soft_deletes(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertCount(0, Product::all());
        $this->assertCount(1, Product::withTrashed()->get());
    }

    #[Test]
    public function price_is_cast_to_decimal(): void
    {
        $product = Product::factory()->create(['price' => 29.99]);

        $this->assertEquals('29.99', $product->price);
    }

    #[Test]
    public function is_active_is_cast_to_boolean(): void
    {
        $product = Product::factory()->create(['is_active' => 1]);

        $this->assertTrue($product->is_active);
    }

    #[Test]
    public function product_category_has_many_products(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->count(2)->create(['product_category_id' => $category->id]);

        $this->assertCount(2, $category->products);
    }

    #[Test]
    public function variant_belongs_to_product(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertInstanceOf(Product::class, $variant->product);
    }

    #[Test]
    public function variant_price_modifier_is_decimal(): void
    {
        $variant = ProductVariant::factory()->create(['price_modifier' => 5.50]);

        $this->assertEquals('5.50', $variant->price_modifier);
    }
}
