<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerStatFactory extends Factory
{
    protected $model = PlayerStat::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'season_id' => Season::factory(),
            'points' => fake()->numberBetween(0, 500),
            'blocks' => fake()->numberBetween(0, 100),
            'aces' => fake()->numberBetween(0, 80),
            'attacks' => fake()->numberBetween(0, 600),
            'receptions' => fake()->numberBetween(0, 400),
        ];
    }
}
