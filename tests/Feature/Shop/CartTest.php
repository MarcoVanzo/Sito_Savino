<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_can_view_cart_page(): void
    {
        $response = $this->get(route('shop.cart'));
        $response->assertStatus(200);
    }

    public function test_user_can_add_product_to_cart(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->post(route('shop.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_cart_count_returns_json(): void
    {
        $response = $this->getJson(route('shop.cart.count'));

        $response->assertOk();
        $response->assertJsonStructure(['count']);
    }
}
