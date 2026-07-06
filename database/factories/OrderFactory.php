<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'shipping_address' => fake()->address(),
            'billing_address' => fake()->address(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Order $order) {
            if (!$order->total_price) {
                $order->forceFill(['total_price' => fake()->randomFloat(2, 10, 500)])->save();
            }
        });
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Cancelled]);
    }
}
