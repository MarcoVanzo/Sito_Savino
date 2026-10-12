<?php

namespace Database\Factories;

use App\Enums\StatoDellaDiretta;
use App\Models\Game;
use App\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveSession>
 */
class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'preroll_attivo' => true,
            'preroll_salta_dopo_secondi' => 5,
            'ciclo_attivo' => true,
            'ciclo_ogni_minuti' => 10,
            'ciclo_durata_secondi' => 15,
            'ciclo_ritardo_minuti' => 5,
            'pausa_durata_predefinita_secondi' => 180,
            'stato' => StatoDellaDiretta::InOnda,
            'versione' => 1,
        ];
    }
}
