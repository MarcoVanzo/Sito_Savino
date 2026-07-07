<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    public function definition(): array
    {
        return [
            'name' => fake()->country(),
            'countries' => ['IT'],
            'flat_rate' => fake()->randomFloat(2, 5, 20),
            'free_threshold' => fake()->randomFloat(2, 50, 200),
            'estimated_days_min' => fake()->numberBetween(1, 3),
            'estimated_days_max' => fake()->numberBetween(4, 7),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
