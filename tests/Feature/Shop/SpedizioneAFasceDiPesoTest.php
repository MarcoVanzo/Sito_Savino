<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * La spedizione costa anche in base al peso del collo.
 *
 * In Italia sono 7,50 € entro i 5 kg e poi si sale per fasce: con la sola
 * tariffa di zona una scatola di dieci maglie viaggiava al prezzo di una
 * sciarpa.
 */
class SpedizioneAFasceDiPesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_il_peso_sceglie_la_fascia(): void
    {
        $zona = $this->zonaItaliana();

        $this->assertSame(7.5, $zona->calculateShippingCost(20, 3));
        $this->assertSame(7.5, $zona->calculateShippingCost(20, 5));
        $this->assertSame(12.0, $zona->calculateShippingCost(20, 5.1));
        $this->assertSame(20.0, $zona->calculateShippingCost(20, 40));
    }

    public function test_senza_fasce_vale_la_tariffa_base(): void
    {
        $zona = ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.90,
            'weight_rates' => null,
            'free_threshold' => null,
        ]);

        $this->assertSame(7.9, $zona->calculateShippingCost(20, 40));
    }

    public function test_la_spedizione_gratuita_batte_le_fasce(): void
    {
        // È una promessa fatta nel carrello: vale qualunque sia il collo.
        $zona = $this->zonaItaliana(['free_threshold' => 100]);

        $this->assertSame(0.0, $zona->calculateShippingCost(150, 40));
    }

    public function test_le_fasce_scritte_in_disordine_valgono_lo_stesso(): void
    {
        $zona = $this->zonaItaliana([
            'weight_rates' => [
                ['max_weight' => null, 'rate' => 20],
                ['max_weight' => 10, 'rate' => 12],
                ['max_weight' => 5, 'rate' => 7.5],
            ],
        ]);

        $this->assertSame(7.5, $zona->calculateShippingCost(20, 2));
        $this->assertSame(20.0, $zona->calculateShippingCost(20, 30));
    }

    public function test_una_fascia_senza_tariffa_non_conta(): void
    {
        $zona = $this->zonaItaliana([
            'weight_rates' => [
                ['max_weight' => 5, 'rate' => null],
                ['max_weight' => 10, 'rate' => 12],
            ],
        ]);

        $this->assertSame(12.0, $zona->calculateShippingCost(20, 2));
    }

    public function test_il_peso_del_carrello_somma_le_quantita(): void
    {
        $this->actingAs(User::factory()->create());

        $maglia = Product::factory()->create(['weight' => 0.25, 'stock' => 10, 'price' => 20]);
        $this->nelCarrello($maglia, 4);

        $this->assertSame(1.0, app(CartService::class)->getCartWeight());
    }

    public function test_un_prodotto_senza_peso_usa_il_ripiego(): void
    {
        SiteSetting::set('shop.default_item_weight_kg', '0.4');
        SiteSetting::clearCache();

        $this->actingAs(User::factory()->create());

        $senzaPeso = Product::factory()->create(['weight' => null, 'stock' => 10, 'price' => 20]);
        $conPeso = Product::factory()->create(['weight' => 0.2, 'stock' => 10, 'price' => 20]);

        $this->nelCarrello($senzaPeso, 2);
        $this->nelCarrello($conPeso, 1);

        $this->assertSame(1.0, app(CartService::class)->getCartWeight());
    }

    public function test_il_checkout_riceve_peso_e_fasce(): void
    {
        $this->zonaItaliana();
        $this->actingAs(User::factory()->create());

        $this->nelCarrello(Product::factory()->create(['weight' => 3, 'stock' => 10, 'price' => 20]), 2);

        $this->get(route('shop.checkout'))
            ->assertOk()
            // I numeri passano da JSON e tornano indifferentemente int o
            // float: conta il valore, non il tipo.
            ->assertInertia(fn ($pagina) => $pagina
                ->where('cartWeight', fn ($peso) => (float) $peso === 6.0)
                ->where('shippingZones.0.weight_rates.0.max_weight', fn ($kg) => (float) $kg === 5.0)
                ->where('shippingZones.0.weight_rates.0.rate', fn ($tariffa) => (float) $tariffa === 7.5),
            );
    }

    private function zonaItaliana(array $attributi = []): ShippingZone
    {
        return ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.90,
            'free_threshold' => null,
            'is_active' => true,
            'weight_rates' => [
                ['max_weight' => 5, 'rate' => 7.5],
                ['max_weight' => 10, 'rate' => 12],
                ['max_weight' => null, 'rate' => 20],
            ],
            ...$attributi,
        ]);
    }

    private function nelCarrello(Product $prodotto, int $quantita): void
    {
        $this->post(route('shop.cart.store'), [
            'product_id' => $prodotto->id,
            'quantity' => $quantita,
        ])->assertRedirect();
    }
}
