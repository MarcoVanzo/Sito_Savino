<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName('female'),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-35 years', '-18 years'),
            'nationality' => fake()->randomElement(['Italia', 'USA', 'Brasile', 'Turchia', 'Serbia']),
        ];
    }
}
