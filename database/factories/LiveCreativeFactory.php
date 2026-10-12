<?php

namespace Database\Factories;

use App\Enums\TipoCreativita;
use App\Enums\UsoCreativita;
use App\Models\LiveCreative;
use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveCreative>
 */
class LiveCreativeFactory extends Factory
{
    protected $model = LiveCreative::class;

    public function definition(): array
    {
        return [
            'sponsor_id' => Sponsor::factory(),
            'nome' => 'Spot '.fake()->word(),
            'uso' => UsoCreativita::Preroll,
            'tipo' => TipoCreativita::Immagine,
            'durata_secondi' => 10,
            'link_url' => 'https://www.example.com/',
            'testo_alternativo' => ['it' => 'Logo dello sponsor', 'en' => 'Sponsor logo'],
            'attiva' => true,
        ];
    }

    public function cornice(): static
    {
        return $this->state(fn () => ['uso' => UsoCreativita::Cornice]);
    }

    public function video(): static
    {
        return $this->state(fn () => ['tipo' => TipoCreativita::Video, 'durata_secondi' => 15]);
    }
}
