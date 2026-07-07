<?php

namespace Tests\Unit\Models;

use App\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShippingZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('shipping_zones_active');
    }

    public function test_active_scope(): void
    {
        $active = ShippingZone::factory()->create(['is_active' => true]);
        $inactive = ShippingZone::factory()->create(['is_active' => false]);

        $results = ShippingZone::active()->pluck('id')->toArray();

        $this->assertContains($active->id, $results);
        $this->assertNotContains($inactive->id, $results);
    }

    public function test_ordered_scope(): void
    {
        $third = ShippingZone::factory()->create(['sort_order' => 30]);
        $first = ShippingZone::factory()->create(['sort_order' => 10]);
        $second = ShippingZone::factory()->create(['sort_order' => 20]);

        $results = ShippingZone::ordered()->pluck('id')->toArray();

        $this->assertEquals($first->id, $results[0]);
        $this->assertEquals($second->id, $results[1]);
        $this->assertEquals($third->id, $results[2]);
    }

    public function test_find_by_country_exact_match(): void
    {
        Cache::forget('shipping_zones_active');
        ShippingZone::factory()->create([
            'countries' => ['IT'],
            'is_active' => true,
            'flat_rate' => 9.90,
        ]);

        $zone = ShippingZone::findByCountry('IT');

        $this->assertNotNull($zone);
        $this->assertContains('IT', $zone->countries);
    }

    public function test_find_by_country_wildcard_fallback(): void
    {
        Cache::forget('shipping_zones_active');
        ShippingZone::factory()->create([
            'countries' => ['*'],
            'is_active' => true,
            'flat_rate' => 25.00,
        ]);

        $zone = ShippingZone::findByCountry('US');

        $this->assertNotNull($zone);
        $this->assertContains('*', $zone->countries);
    }

    public function test_find_by_country_returns_null_when_no_match(): void
    {
        Cache::forget('shipping_zones_active');
        ShippingZone::factory()->create([
            'countries' => ['DE'],
            'is_active' => true,
        ]);

        $zone = ShippingZone::findByCountry('JP');

        $this->assertNull($zone);
    }

    public function test_calculate_shipping_cost_flat_rate(): void
    {
        $zone = ShippingZone::factory()->create([
            'flat_rate' => 9.90,
            'free_threshold' => 100.00,
        ]);

        $this->assertEquals(9.90, $zone->calculateShippingCost(50.00));
    }

    public function test_calculate_shipping_cost_free_above_threshold(): void
    {
        $zone = ShippingZone::factory()->create([
            'flat_rate' => 9.90,
            'free_threshold' => 100.00,
        ]);

        $this->assertEquals(0.00, $zone->calculateShippingCost(150.00));
    }
}
