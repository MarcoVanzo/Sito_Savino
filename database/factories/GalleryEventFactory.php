<?php

namespace Database\Factories;

use App\Models\GalleryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryEventFactory extends Factory
{
    protected $model = GalleryEvent::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'event_date' => fake()->dateTimeBetween('-1 year', '+1 month'),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['match', 'event', 'training', 'press']),
            'is_active' => true,
        ];
    }
}
