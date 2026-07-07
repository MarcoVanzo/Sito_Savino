<?php

namespace Database\Factories;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryImageFactory extends Factory
{
    protected $model = GalleryImage::class;

    public function definition(): array
    {
        return [
            'gallery_event_id' => GalleryEvent::factory(),
            'title' => fake()->sentence(3),
            'category' => fake()->randomElement(['match', 'event', 'training', 'press']),
            'sort_order' => fake()->numberBetween(0, 50),
            'file_hash' => fake()->sha256(),
            'is_active' => true,
            'needs_review' => false,
            'ai_analyzed_at' => null,
        ];
    }
}
