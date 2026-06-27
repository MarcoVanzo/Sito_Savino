<?php

namespace Database\Factories;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'url' => fake()->url(),
            'tier' => fake()->randomElement(['main', 'gold', 'silver', 'technical', 'standard']),
        ];
    }
}
