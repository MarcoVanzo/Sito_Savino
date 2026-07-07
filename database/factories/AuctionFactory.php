<?php

namespace Database\Factories;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'size' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
            'starting_price' => fake()->randomFloat(2, 10, 100),
            'current_bid' => null,
            'reserve_price' => null,
            'bid_increment' => fake()->randomFloat(2, 1, 10),
            'max_bid_jump' => fake()->randomFloat(2, 50, 200),
            'start_date' => now()->addDays(fake()->numberBetween(1, 7)),
            'end_date' => now()->addDays(fake()->numberBetween(8, 14)),
            'is_charity' => false,
            'charity_description' => null,
        ];
    }

    /**
     * Configure the model factory.
     * status and winner_user_id are not mass-assignable, so we forceFill them after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Auction $auction) {
            if (! $auction->status) {
                $auction->forceFill([
                    'status' => AuctionStatus::Scheduled,
                ])->save();
            }
        });
    }

    /**
     * Indicate that the auction is active.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->subDays(fake()->numberBetween(1, 3)),
            'end_date' => now()->addDays(fake()->numberBetween(1, 7)),
        ])->afterCreating(function (Auction $auction) {
            $auction->forceFill([
                'status' => AuctionStatus::Active,
            ])->save();
        });
    }

    /**
     * Indicate that the auction has ended.
     */
    public function ended(): static
    {
        return $this->state(fn () => [
            'start_date' => now()->subDays(fake()->numberBetween(8, 14)),
            'end_date' => now()->subDays(fake()->numberBetween(1, 3)),
        ])->afterCreating(function (Auction $auction) {
            $auction->forceFill([
                'status' => AuctionStatus::Ended,
            ])->save();
        });
    }

    /**
     * Indicate that the auction has a reserve price.
     */
    public function withReserve(): static
    {
        return $this->state(fn () => [
            'reserve_price' => fake()->randomFloat(2, 50, 500),
        ]);
    }
}
