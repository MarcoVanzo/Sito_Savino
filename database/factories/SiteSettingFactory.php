<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteSettingFactory extends Factory
{
    protected $model = SiteSetting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->sentence(),
            'type' => fake()->randomElement(['text', 'boolean', 'json']),
            'group' => fake()->randomElement(['general', 'brand', 'footer', 'shop', 'social', 'seo']),
            'label' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
