<?php

namespace Tests\Feature\Lvf;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Services\Lvf\LvfPhaseLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LvfPhaseLabelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_fase_resta_in_italiano_sul_sito_italiano(): void
    {
        $this->app->setLocale('it');

        $this->assertSame('Andata', LvfPhaseLabel::translate('Andata'));
        $this->assertSame('Ritorno', LvfPhaseLabel::translate('Ritorno'));
    }

    #[Test]
    public function la_fase_viene_tradotta_sul_sito_inglese(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('First leg', LvfPhaseLabel::translate('Andata'));
        $this->assertSame('Second leg', LvfPhaseLabel::translate('Ritorno'));
    }

    #[Test]
    public function una_fase_sconosciuta_resta_quella_grezza_della_lega(): void
    {
        // La Lega può introdurre fasi nuove (playoff, poule salvezza): meglio
        // mostrarle in italiano che stampare la chiave di traduzione.
        $this->app->setLocale('en');

        $this->assertSame('Poule Salvezza', LvfPhaseLabel::translate('Poule Salvezza'));
        $this->assertNull(LvfPhaseLabel::translate(null));
        $this->assertNull(LvfPhaseLabel::translate('  '));
    }

    #[Test]
    public function la_pagina_inglese_dei_risultati_mostra_la_fase_tradotta(): void
    {
        $season = Season::create(['name' => '2026/2027', 'is_current' => true, 'lvf_season_year' => 2026]);
        $own = Team::create(['name' => 'Savino Del Bene Volley', 'slug' => 'sdb', 'is_internal' => true]);
        $rival = Team::create(['name' => 'Il Bisonte Firenze', 'slug' => 'bisonte', 'is_internal' => false]);

        Game::create([
            'season_id' => $season->id,
            'home_team_id' => $own->id,
            'away_team_id' => $rival->id,
            'match_date' => now()->addWeek(),
            'status' => GameStatus::Scheduled,
            'competition_type' => CompetitionType::Championship,
            'lvf_match_id' => 747982,
            'matchday' => 3,
            // Il dato salvato resta quello grezzo della Lega, in italiano.
            'phase' => 'Ritorno',
        ]);

        $response = $this->get('/en/stagione/risultati');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->where('games.0.matchdayLabel', 'Round 3 · Second leg')
                ->where('games.0.phase', 'Ritorno')
        );
    }
}
