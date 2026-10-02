<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La soglia globale della spedizione gratuita vale davvero (decisione di
 * Marco, 2/10/2026).
 *
 * Il carrello annunciava `shop.free_shipping_threshold` ma il checkout
 * applicava solo la soglia della zona: il cliente si vedeva promettere una
 * consegna gratuita che poi pagava. Ora, se compilata, vale per tutte le zone
 * al posto della loro; vuota vale la soglia della zona. Carrello, checkout,
 * aste e pagina Spedizioni devono dire lo stesso numero.
 */
class SogliaGlobaleDiSpedizioneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::table('shipping_zones')->delete();
        DB::table('site_settings')->where('key', 'shop.free_shipping_threshold')->delete();
        SiteSetting::clearCache();
        Cache::flush();
    }

    #[Test]
    public function la_soglia_globale_vale_al_posto_di_quella_della_zona(): void
    {
        $zona = $this->zona(['free_threshold' => 100]);
        $this->sogliaGlobale('60');

        $this->assertSame(60.0, $zona->sogliaGratuita());
        $this->assertSame(0.0, $zona->calculateShippingCost(70));
        $this->assertSame(7.9, $zona->calculateShippingCost(59.99));
    }

    #[Test]
    public function la_soglia_globale_vale_anche_per_le_zone_senza_soglia(): void
    {
        $zona = $this->zona(['free_threshold' => null]);
        $this->sogliaGlobale('80');

        $this->assertSame(0.0, $zona->calculateShippingCost(80));
    }

    #[Test]
    public function la_soglia_globale_piu_alta_toglie_la_gratuita_della_zona(): void
    {
        // Non e' "la piu' conveniente delle due": e' quella globale, punto.
        $zona = $this->zona(['free_threshold' => 50]);
        $this->sogliaGlobale('150');

        $this->assertSame(7.9, $zona->calculateShippingCost(100));
    }

    #[Test]
    #[DataProvider('globaliVuote')]
    public function vuota_o_a_zero_vale_la_soglia_della_zona(?string $valore): void
    {
        $zona = $this->zona(['free_threshold' => 100]);

        if ($valore !== null) {
            $this->sogliaGlobale($valore);
        }

        $this->assertSame(100.0, $zona->sogliaGratuita());
        $this->assertSame(7.9, $zona->calculateShippingCost(90));
        $this->assertSame(0.0, $zona->calculateShippingCost(100));
    }

    /** @return array<string, array{?string}> */
    public static function globaliVuote(): array
    {
        return [
            'riga assente' => [null],
            'vuota' => [''],
            'zero' => ['0'],
            'negativa' => ['-5'],
            'non numerica' => ['abc'],
        ];
    }

    #[Test]
    public function zona_con_soglia_a_zero_e_senza_globale_non_ha_soglia(): void
    {
        $zona = $this->zona(['free_threshold' => 0]);

        $this->assertNull($zona->sogliaGratuita());
        $this->assertSame(7.9, $zona->calculateShippingCost(500));
    }

    #[Test]
    public function carrello_checkout_e_pagina_spedizioni_dicono_la_stessa_soglia(): void
    {
        $this->zona(['free_threshold' => 100]);
        $this->sogliaGlobale('60');

        $this->actingAs(User::factory()->create());
        $prodotto = Product::factory()->create(['weight' => 0.5, 'stock' => 10, 'price' => 35]);
        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 2])->assertRedirect();

        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('freeShippingThreshold', fn ($soglia) => (float) $soglia === 60.0));

        // 70 € di spesa: sopra la globale (60), sotto quella della zona (100).
        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('shippingZones.0.free_threshold', fn ($soglia) => (float) $soglia === 60.0)
                ->where('shippingZones.0.costo_spedizione', fn ($costo) => (float) $costo === 0.0));

        $this->get('/spedizioni')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('zoneDiSpedizione.0.soglia_gratuita', fn ($soglia) => (float) $soglia === 60.0));
    }

    #[Test]
    public function senza_globale_il_checkout_usa_la_soglia_della_zona(): void
    {
        $this->zona(['free_threshold' => 100]);

        $this->actingAs(User::factory()->create());
        $prodotto = Product::factory()->create(['weight' => 0.5, 'stock' => 10, 'price' => 35]);
        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 2])->assertRedirect();

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('shippingZones.0.free_threshold', fn ($soglia) => (float) $soglia === 100.0)
                ->where('shippingZones.0.costo_spedizione', fn ($costo) => (float) $costo === 7.9));
    }

    private function zona(array $attributi): ShippingZone
    {
        return ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.90,
            'weight_rates' => [],
            'is_active' => true,
            ...$attributi,
        ]);
    }

    private function sogliaGlobale(string $valore): void
    {
        SiteSetting::set('shop.free_shipping_threshold', $valore, 'shop');
        SiteSetting::clearCache();
    }
}
