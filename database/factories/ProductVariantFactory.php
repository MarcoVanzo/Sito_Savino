<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'color' => fake()->safeColorName(),
            'sku' => strtoupper(fake()->bothify('VAR-??-####')),
            'price_modifier' => fake()->randomFloat(2, -10, 20),
            'stock' => fake()->numberBetween(0, 50),
        ];
    }
}
