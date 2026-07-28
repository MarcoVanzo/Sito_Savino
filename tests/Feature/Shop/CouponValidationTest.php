<?php

namespace Tests\Feature\Shop;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // La validità di un coupon è una finestra temporale: congelando il tempo
        // le finestre dichiarate qui sotto restano tali anche se la suite è
        // lenta, e lo scadere si simula con travel().
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    /**
     * Autentica un cliente e gli mette in carrello un prodotto da 100 €.
     * Serve a superare il guard "carrello vuoto" dell'endpoint e ottenere
     * una risposta che dipenda davvero dal coupon. Il carrello è legato
     * all'utente e non alla sessione, perché con il driver di sessione
     * `array` l'id di sessione cambia a ogni richiesta del test.
     */
    private function fillCartWithHundredEuros(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $this->actingAs(User::factory()->create());

        $this->post(route('shop.cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();
    }

    /**
     * Coupon da 15 € fissi, valido nella finestra indicata.
     */
    private function fixedCoupon(CarbonInterface $from, CarbonInterface $until): Coupon
    {
        return Coupon::factory()->create([
            'type' => CouponType::Fixed,
            'value' => 15,
            'max_discount' => null,
            'min_order_amount' => 50,
            'max_uses_per_user' => 0,
            'is_active' => true,
            'valid_from' => $from,
            'valid_until' => $until,
        ]);
    }

    public function test_valid_coupon_returns_the_calculated_discount(): void
    {
        $this->fillCartWithHundredEuros();

        $coupon = $this->fixedCoupon(now()->subHour(), now()->addHour());

        $response = $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => $coupon->code,
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'discount' => 15,
            'coupon_code' => $coupon->code,
        ]);
    }

    public function test_coupon_stops_being_accepted_once_its_window_closes(): void
    {
        $this->fillCartWithHundredEuros();

        $coupon = $this->fixedCoupon(now()->subHour(), now()->addHour());

        // Un minuto oltre la scadenza: stesso carrello, stesso coupon,
        // cambia solo l'orologio.
        $this->travel(61)->minutes();

        $response = $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => $coupon->code,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            // Il messaggio distingue il rifiuto per scadenza dal 422 generico
            // di "carrello vuoto": senza questo controllo il test passerebbe
            // anche a carrello non popolato.
            'message' => __('messages.checkout.coupon_not_applicable'),
        ]);
    }

    public function test_coupon_is_not_accepted_before_its_window_opens(): void
    {
        $this->fillCartWithHundredEuros();

        $coupon = $this->fixedCoupon(now()->addHour(), now()->addDays(2));

        $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => $coupon->code,
        ])->assertStatus(422)->assertJson([
            'valid' => false,
            'message' => __('messages.checkout.coupon_not_applicable'),
        ]);

        // Aperta la finestra, lo stesso coupon viene accettato.
        $this->travel(61)->minutes();

        $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => $coupon->code,
        ])->assertOk()->assertJson(['valid' => true, 'discount' => 15]);
    }

    public function test_nonexistent_coupon_returns_error(): void
    {
        $this->fillCartWithHundredEuros();

        $response = $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => 'INVALID-CODE-999',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'message' => __('messages.checkout.invalid_coupon'),
        ]);
    }

    public function test_expired_coupon_returns_error(): void
    {
        $this->fillCartWithHundredEuros();

        $coupon = Coupon::factory()->expired()->create([
            'min_order_amount' => 50,
            'max_uses_per_user' => 0,
        ]);

        $response = $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => $coupon->code,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'message' => __('messages.checkout.coupon_not_applicable'),
        ]);
    }

    public function test_empty_cart_is_rejected_before_the_coupon_is_even_looked_up(): void
    {
        $coupon = $this->fixedCoupon(now()->subDay(), now()->addDays(30));

        $response = $this->postJson(route('shop.checkout.validate-coupon'), [
            'coupon_code' => $coupon->code,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'message' => __('messages.checkout.cart_empty'),
        ]);
    }
}
