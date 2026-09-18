<?php

namespace Tests\Feature\Shop;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Payments\PayPalPaymentService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il passaggio dal checkout al gateway.
 *
 * Il modulo di checkout è un form Inertia: la POST parte come XHR con
 * l'header X-Inertia. Un 302 verso paypal.com o stripe.com viene seguito
 * dalla stessa XHR, che finisce contro il CORS del gateway e muore in
 * console — l'ordine è già creato e la merce riservata, ma il cliente resta
 * sulla pagina senza pagare. Per una navigazione fuori dall'applicazione
 * Inertia vuole 409 + X-Inertia-Location.
 */
class CheckoutGatewayRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function carrelloPronto(User $user): void
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 5, 'free_threshold' => null]);

        $product = Product::factory()->create(['stock' => 10, 'price' => 20]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function datiCheckout(string $gateway): array
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
            'payment_gateway' => $gateway,
            'privacy_accepted' => true,
            'phone' => '3331234567',
            'codice_fiscale' => 'RSSMRC85M01D612H',
        ];
    }

    public function test_il_checkout_paypal_chiede_al_client_inertia_di_uscire_dal_sito(): void
    {
        $user = User::factory()->create();
        $this->carrelloPronto($user);

        $this->mock(PayPalPaymentService::class)
            ->shouldReceive('createSession')
            ->once()
            ->andReturn('https://www.paypal.com/checkoutnow?token=TOKEN-TEST');

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('shop.checkout.store'), $this->datiCheckout('paypal'));

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://www.paypal.com/checkoutnow?token=TOKEN-TEST');
    }

    public function test_il_checkout_stripe_chiede_al_client_inertia_di_uscire_dal_sito(): void
    {
        $user = User::factory()->create();
        $this->carrelloPronto($user);

        $this->mock(StripePaymentService::class)
            ->shouldReceive('createSession')
            ->once()
            ->andReturn('https://checkout.stripe.com/c/pay/TEST');

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('shop.checkout.store'), $this->datiCheckout('stripe'));

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/TEST');
    }

    public function test_un_gateway_senza_credenziali_non_viene_offerto(): void
    {
        $user = User::factory()->create();
        $this->carrelloPronto($user);

        // In produzione le chiavi di Stripe non ci sono: il metodo compariva
        // lo stesso fra le scelte e il cliente lo scopriva dopo, con l'ordine
        // già creato e la merce riservata.
        config()->set('services.stripe.secret', '');

        $this->actingAs($user)
            ->get(route('shop.checkout'))
            ->assertInertia(fn ($page) => $page
                ->where('paymentGateways', fn ($gateways) => collect($gateways)->pluck('value')->doesntContain('stripe')));

        $this->actingAs($user)
            ->post(route('shop.checkout.store'), $this->datiCheckout('stripe'))
            ->assertSessionHasErrors('payment_gateway');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_senza_inertia_resta_un_redirect_normale(): void
    {
        $user = User::factory()->create();
        $this->carrelloPronto($user);

        $this->mock(PayPalPaymentService::class)
            ->shouldReceive('createSession')
            ->once()
            ->andReturn('https://www.paypal.com/checkoutnow?token=TOKEN-TEST');

        $response = $this->actingAs($user)
            ->post(route('shop.checkout.store'), $this->datiCheckout('paypal'));

        $response->assertRedirect('https://www.paypal.com/checkoutnow?token=TOKEN-TEST');
    }
}
