<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BidFactory extends Factory
{
    protected $model = Bid::class;

    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'placed_at' => now(),
        ];
    }

    /**
     * Configure the model factory.
     * is_valid is not mass-assignable, so we forceFill it after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Bid $bid) {
            $bid->forceFill([
                'is_valid' => true,
            ])->save();
        });
    }
}
