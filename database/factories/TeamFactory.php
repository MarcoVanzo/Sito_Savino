<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->city().' Volley';

        return [
            'name' => $name,
            // `teams.slug` è UNIQUE e fake()->city() può ripetersi: aggiungiamo
            // un suffisso univoco per evitare collisioni nei test/seed.
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
            'category' => 'A1',
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }
}
