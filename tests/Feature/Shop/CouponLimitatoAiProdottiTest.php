<?php

namespace Tests\Feature\Shop;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un codice promozionale può valere solo su alcuni prodotti.
 *
 * La redazione usa codici legati a un singolo articolo — il compleanno di
 * un'atleta, la maglia di una gara — e finora lo sconto si applicava a tutto
 * il carrello: chi usava quel codice scontava anche il resto dell'ordine.
 */
class CouponLimitatoAiProdottiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->actingAs(User::factory()->create());
    }

    public function test_lo_sconto_si_calcola_solo_sui_prodotti_ammessi(): void
    {
        $maglia = $this->prodotto(100);
        $sciarpa = $this->prodotto(50);

        $this->nelCarrello($maglia);
        $this->nelCarrello($sciarpa);

        $coupon = $this->couponDaDieciPerCento();
        $coupon->products()->attach($maglia);

        // Il 10% dei 100 € della maglia, non dei 150 € del carrello.
        $this->postJson(route('shop.checkout.validate-coupon'), ['coupon_code' => $coupon->code])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 10]);
    }

    public function test_senza_i_prodotti_ammessi_il_codice_viene_rifiutato(): void
    {
        $maglia = $this->prodotto(100);
        $sciarpa = $this->prodotto(50);

        $this->nelCarrello($sciarpa);

        $coupon = $this->couponDaDieciPerCento();
        $coupon->products()->attach($maglia);

        $this->postJson(route('shop.checkout.validate-coupon'), ['coupon_code' => $coupon->code])
            ->assertStatus(422)
            ->assertJson([
                'valid' => false,
                'message' => __('messages.checkout.coupon_products_missing'),
            ]);
    }

    public function test_il_limite_puo_essere_una_categoria(): void
    {
        $kit = ProductCategory::factory()->create();
        $maglia = $this->prodotto(100, $kit);
        $sciarpa = $this->prodotto(50);

        $this->nelCarrello($maglia);
        $this->nelCarrello($sciarpa);

        $coupon = $this->couponDaDieciPerCento();
        $coupon->categories()->attach($kit);

        $this->postJson(route('shop.checkout.validate-coupon'), ['coupon_code' => $coupon->code])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 10]);
    }

    public function test_un_coupon_senza_limiti_vale_su_tutto_il_carrello(): void
    {
        $this->nelCarrello($this->prodotto(100));
        $this->nelCarrello($this->prodotto(50));

        $coupon = $this->couponDaDieciPerCento();

        $this->postJson(route('shop.checkout.validate-coupon'), ['coupon_code' => $coupon->code])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 15]);
    }

    public function test_l_ordine_minimo_guarda_tutto_il_carrello(): void
    {
        // La soglia è una spesa minima, non un vincolo su cosa si compra:
        // i 60 € della maglia da soli non la raggiungerebbero, il carrello sì.
        $maglia = $this->prodotto(60);
        $this->nelCarrello($maglia);
        $this->nelCarrello($this->prodotto(50));

        $coupon = $this->couponDaDieciPerCento(['min_order_amount' => 100]);
        $coupon->products()->attach($maglia);

        $this->postJson(route('shop.checkout.validate-coupon'), ['coupon_code' => $coupon->code])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount' => 6]);
    }

    private function prodotto(float $prezzo, ?ProductCategory $categoria = null): Product
    {
        return Product::factory()->create([
            'price' => $prezzo,
            'stock' => 10,
            'product_category_id' => $categoria?->id ?? ProductCategory::factory(),
        ]);
    }

    private function nelCarrello(Product $prodotto): void
    {
        $this->post(route('shop.cart.store'), [
            'product_id' => $prodotto->id,
            'quantity' => 1,
        ])->assertRedirect();
    }

    private function couponDaDieciPerCento(array $attributi = []): Coupon
    {
        return Coupon::factory()->create([
            'type' => CouponType::Percentage,
            'value' => 10,
            'max_discount' => null,
            'min_order_amount' => null,
            'max_uses_per_user' => 0,
            'is_active' => true,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHour(),
            ...$attributi,
        ]);
    }
}
