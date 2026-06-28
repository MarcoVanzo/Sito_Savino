<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        HeroSlide::updateOrCreate(
            ['title' => 'SAVINO DEL BENE VOLLEY'],
            [
                'subtitle' => 'Scatena la Potenza.',
                'cta_text' => 'Scopri la Squadra',
                'cta_url' => '/stagione',
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        HeroSlide::updateOrCreate(
            ['title' => 'CAMPIONATO SERIE A1'],
            [
                'subtitle' => 'Segui tutte le partite',
                'cta_text' => 'Calendario',
                'cta_url' => '/risultati',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }
}
