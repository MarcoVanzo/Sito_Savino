<?php

namespace Database\Factories;

use App\Models\AnalyticsSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsSite>
 */
class AnalyticsSiteFactory extends Factory
{
    protected $model = AnalyticsSite::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'property_id' => (string) $this->faker->unique()->numberBetween(100000000, 999999999),
            'url' => $this->faker->url(),
            'sort' => 0,
        ];
    }
}
