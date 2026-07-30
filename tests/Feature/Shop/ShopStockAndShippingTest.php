<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Copre i due difetti dello shop riscontrati in produzione: i prodotti con
 * varianti mostrati "esaurito" pur avendo magazzino, e gli importi di
 * spedizione consegnati al client come stringhe (il totale del checkout
 * diventava una concatenazione).
 */
class ShopStockAndShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_a_variable_product_reports_the_stock_of_its_variants(): void
    {
        $product = Product::factory()->create([
            'type' => ProductType::Variable,
            'stock' => 0,
        ]);

        foreach (['S', 'M', 'L'] as $size) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'size' => $size,
                'stock' => 10,
            ]);
        }

        $this->assertSame(30, $product->availableStock());
    }

    public function test_a_simple_product_still_reports_its_own_stock(): void
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple,
            'stock' => 7,
        ]);

        $this->assertSame(7, $product->availableStock());
    }

    public function test_the_shop_grid_does_not_mark_a_stocked_variable_product_as_out_of_stock(): void
    {
        $category = ProductCategory::factory()->create();

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'type' => ProductType::Variable,
            'stock' => 0,
            'is_active' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'size' => 'M',
            'stock' => 12,
        ]);

        $response = $this->get(route('shop'));

        $response->assertStatus(200);

        $products = collect($response->viewData('page')['props']['allProducts'] ?? []);
        $card = $products->firstWhere('id', $product->id);

        $this->assertNotNull($card, 'Il prodotto non compare nella vetrina.');
        $this->assertSame(12, $card['stock']);
    }

    public function test_shipping_zones_reach_the_client_as_numbers(): void
    {
        ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.90,
            'free_threshold' => 50,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'type' => ProductType::Simple,
            'stock' => 5,
            'price' => 4.00,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('shop.checkout'));
        $response->assertStatus(200);

        $zones = $response->viewData('page')['props']['shippingZones'] ?? [];

        $this->assertNotEmpty($zones, 'Nessuna zona di spedizione consegnata al client.');

        foreach ($zones as $zone) {
            $this->assertIsFloat($zone['flat_rate']);

            if ($zone['free_threshold'] !== null) {
                $this->assertIsFloat($zone['free_threshold']);
            }
        }
    }
}
