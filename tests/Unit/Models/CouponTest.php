<?php

namespace Tests\Unit\Models;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_has_many_usages(): void
    {
        $coupon = Coupon::factory()->create();
        CouponUsage::factory()->create(['coupon_id' => $coupon->id]);

        $this->assertCount(1, $coupon->usages);
        $this->assertInstanceOf(CouponUsage::class, $coupon->usages->first());
    }

    public function test_active_scope(): void
    {
        $active = Coupon::factory()->create(['is_active' => true]);
        $inactive = Coupon::factory()->inactive()->create();

        $results = Coupon::active()->pluck('id')->toArray();

        $this->assertContains($active->id, $results);
        $this->assertNotContains($inactive->id, $results);
    }

    public function test_is_valid_for_order_returns_false_for_inactive(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => false]);

        $this->assertFalse($coupon->isValidForOrder(100.00));
    }

    public function test_is_valid_for_order_returns_false_when_expired(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'valid_until' => now()->subDay(),
        ]);

        $this->assertFalse($coupon->isValidForOrder(100.00));
    }

    public function test_is_valid_for_order_returns_false_below_min_amount(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'min_order_amount' => 50.00,
            'valid_until' => now()->addDays(30),
        ]);

        $this->assertFalse($coupon->isValidForOrder(30.00));
    }

    public function test_is_valid_for_order_returns_false_when_max_uses_reached(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'max_uses' => 5,
            'used_count' => 5,
            'valid_until' => now()->addDays(30),
            'min_order_amount' => null,
        ]);

        $this->assertFalse($coupon->isValidForOrder(100.00));
    }

    public function test_is_valid_for_order_returns_true_for_valid_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'max_uses' => 100,
            'used_count' => 0,
            'min_order_amount' => 10.00,
        ]);

        $this->assertTrue($coupon->isValidForOrder(100.00));
    }

    public function test_calculate_discount_percentage(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => CouponType::Percentage,
            'value' => 10.00,
            'max_discount' => null,
        ]);

        $this->assertEquals(10.00, $coupon->calculateDiscount(100.00));
    }

    public function test_calculate_discount_fixed(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => CouponType::Fixed,
            'value' => 15.00,
        ]);

        $this->assertEquals(15.00, $coupon->calculateDiscount(100.00));
    }

    public function test_calculate_discount_percentage_capped(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => CouponType::Percentage,
            'value' => 50.00,
            'max_discount' => 20.00,
        ]);

        $this->assertEquals(20.00, $coupon->calculateDiscount(100.00));
    }

    public function test_calculate_discount_cannot_exceed_subtotal(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => CouponType::Fixed,
            'value' => 200.00,
        ]);

        $this->assertEquals(100.00, $coupon->calculateDiscount(100.00));
    }

    public function test_increment_usage(): void
    {
        $coupon = Coupon::factory()->create(['used_count' => 0]);

        $coupon->incrementUsage();
        $coupon->refresh();

        $this->assertEquals(1, $coupon->used_count);
    }
}
