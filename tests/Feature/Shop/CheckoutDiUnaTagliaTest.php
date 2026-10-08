<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Payments\PayPalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il 02/10/2026 l'ordine di una maglia con taglie falliva con l'errore
 * generico del checkout: la taglia era disponibile, ma il riepilogo
 * `products.stock` del padre era a 0 e lo scarico lo scalava con la guardia
 * contro il negativo.
 */
class CheckoutDiUnaTagliaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function datiCheckout(): array
    {
        return [
            'shipping_first_name' => 'Marco',
            'shipping_last_name' => 'Rossi',
            'shipping_street' => 'Via Roma 1',
            'shipping_city' => 'Firenze',
            'shipping_zip_code' => '50100',
            'shipping_province' => 'FI',
            'country' => 'IT',
            'billing_same_as_shipping' => true,
            'payment_gateway' => 'paypal',
            'privacy_accepted' => true,
            'phone' => '3331234567',
            'codice_fiscale' => 'RSSMRC85M01D612H',
        ];
    }

    private function magliaConTaglie(): array
    {
        $maglia = Product::factory()->create(['type' => ProductType::Variable, 'price' => 40]);
        $taglia = ProductVariant::factory()->create(['product_id' => $maglia->id, 'stock' => 2, 'price_modifier' => 0]);
        ProductVariant::factory()->create(['product_id' => $maglia->id, 'stock' => 3]);

        // Il riepilogo rimasto indietro, com'era in produzione.
        Product::whereKey($maglia->id)->toBase()->update(['stock' => 0]);

        return [$maglia, $taglia];
    }

    #[Test]
    public function si_ordina_una_taglia_disponibile_anche_col_riepilogo_a_zero(): void
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 5, 'free_threshold' => null]);
        [$maglia, $taglia] = $this->magliaConTaglie();
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $maglia->id,
            'product_variant_id' => $taglia->id,
            'quantity' => 1,
        ]);

        $this->mock(PayPalPaymentService::class)
            ->shouldReceive('createSession')
            ->once()
            ->andReturn('https://www.paypal.com/checkoutnow?token=TOKEN-TEST');

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('shop.checkout.store'), $this->datiCheckout())
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame($taglia->id, Order::first()->items()->value('product_variant_id'));
        $this->assertSame(1, (int) $taglia->fresh()->stock);
        $this->assertSame(4, (int) $maglia->fresh()->stock);
    }

    #[Test]
    public function un_prodotto_con_taglie_non_entra_nel_carrello_senza_taglia(): void
    {
        [$maglia] = $this->magliaConTaglie();

        $this->post(route('shop.cart.store'), ['product_id' => $maglia->id, 'quantity' => 1])
            ->assertSessionHas('error', __('messages.cart.variant_required'));

        $this->assertDatabaseCount('cart_items', 0);
    }
}
