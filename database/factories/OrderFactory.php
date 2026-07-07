<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shipping_address' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'zip_code' => fake()->postcode(),
                'province' => fake()->state(),
            ],
            'billing_address' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'zip_code' => fake()->postcode(),
                'province' => fake()->state(),
            ],
            'country' => fake()->randomElement(['IT', 'DE', 'FR', 'ES']),
            'order_token' => (string) Str::uuid(),
        ];
    }

    /**
     * Configure the model factory.
     * status is not mass-assignable, so we forceFill it after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            $updates = [];

            if (! $order->total_price) {
                $updates['total_price'] = fake()->randomFloat(2, 10, 500);
            }

            // status is not mass-assignable, default to Pending
            if (! $order->status) {
                $updates['status'] = OrderStatus::Pending;
            }

            if ($updates) {
                $order->forceFill($updates)->save();
            }
        });
    }

    /**
     * Indicate that the order is paid.
     * Uses afterCreating because status is not mass-assignable.
     */
    public function paid(): static
    {
        return $this->afterCreating(function (Order $order) {
            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ])->save();
        });
    }

    /**
     * Indicate that the order is cancelled.
     * Uses afterCreating because status is not mass-assignable.
     */
    public function cancelled(): static
    {
        return $this->afterCreating(function (Order $order) {
            $order->forceFill([
                'status' => OrderStatus::Cancelled,
            ])->save();
        });
    }
}
