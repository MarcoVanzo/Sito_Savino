<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'match_date' => fake()->dateTimeBetween('-6 months', '+6 months'),
            'status' => GameStatus::Scheduled,
            'location' => fake()->city().' - PalaVolley',
            'competition_type' => fake()->randomElement(['Campionato', 'Coppa Italia', 'Champions League', 'Amichevole']),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => GameStatus::Completed,
            'home_score' => fake()->numberBetween(0, 3),
            'away_score' => fake()->numberBetween(0, 3),
        ]);
    }
}
