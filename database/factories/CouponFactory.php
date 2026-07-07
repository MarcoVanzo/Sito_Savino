<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'type' => CouponType::Percentage,
            'value' => fake()->randomFloat(2, 5, 30),
            'max_discount' => fake()->randomFloat(2, 10, 50),
            'min_order_amount' => fake()->randomFloat(2, 20, 100),
            'max_uses' => fake()->numberBetween(10, 100),
            'max_uses_per_user' => fake()->numberBetween(1, 3),
            'valid_from' => now(),
            'valid_until' => now()->addDays(30),
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the coupon is percentage-based.
     */
    public function percentage(): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Percentage,
            'value' => fake()->randomFloat(2, 5, 50),
            'max_discount' => fake()->randomFloat(2, 10, 100),
        ]);
    }

    /**
     * Indicate that the coupon is a fixed amount.
     */
    public function fixed(): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Fixed,
            'value' => fake()->randomFloat(2, 5, 50),
            'max_discount' => null,
        ]);
    }

    /**
     * Indicate that the coupon is expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->subDays(30),
            'valid_until' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the coupon is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
