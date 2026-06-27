<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class RosterFactory extends Factory
{
    protected $model = Roster::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'team_id' => Team::factory(),
            'season_id' => Season::factory(),
            'jersey_number' => fake()->numberBetween(1, 30),
            'role' => fake()->randomElement(['palleggiatrice', 'schiacciatrice', 'opposto', 'centrale', 'libero']),
            'height_cm' => fake()->numberBetween(165, 200),
            'is_captain' => false,
        ];
    }

    public function captain(): static
    {
        return $this->state(fn () => ['is_captain' => true]);
    }
}
