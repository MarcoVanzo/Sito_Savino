<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'session_id' => fake()->uuid(),
            'user_id' => User::factory(),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }
}
