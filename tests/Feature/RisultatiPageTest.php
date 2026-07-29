<?php

namespace Tests\Feature;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pagina risultati mostrava una classifica e delle partite inventate: la
 * prop `standings` non era passata da nessun controller, quindi il fallback sui
 * dati segnaposto era sempre attivo e il pubblico vedeva "Squadra A" e
 * "Squadra B" sotto il titolo "Classifica".
 */
class RisultatiPageTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Team $savino;

    private Team $rival;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->season = Season::create(['name' => '2026/2027', 'is_current' => true]);
        $this->savino = Team::create(['name' => 'Savino Del Bene Volley', 'slug' => 'sdb', 'is_internal' => true]);
        $this->rival = Team::create(['name' => 'Il Bisonte Firenze', 'slug' => 'bisonte', 'is_internal' => false]);
    }

    private function game(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'season_id' => $this->season->id,
            'home_team_id' => $this->savino->id,
            'away_team_id' => $this->rival->id,
            'match_date' => now()->addWeek(),
            'status' => GameStatus::Scheduled,
            'competition_type' => CompetitionType::Championship,
        ], $attributes));
    }

    private function standing(Team $team, int $position, int $points): Standing
    {
        return Standing::create([
            'season_id' => $this->season->id,
            'team_id' => $team->id,
            'competition_type' => CompetitionType::Championship,
            'position' => $position,
            'points' => $points,
            'played' => 10,
            'won' => 8,
            'lost' => 2,
            'sets_won' => 26,
            'sets_lost' => 12,
        ]);
    }

    #[Test]
    public function senza_dati_non_mostra_una_classifica_inventata(): void
    {
        $response = $this->get(route('stagione.risultati'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Public/Risultati')
            ->where('standings', [])
            ->where('games', [])
        );

        // I nomi segnaposto non devono comparire da nessuna parte.
        $response->assertDontSee('Squadra A');
        $response->assertDontSee('Squadra B');
    }

    #[Test]
    public function mostra_la_classifica_reale_ordinata_per_posizione(): void
    {
        $this->standing($this->rival, 2, 24);
        $this->standing($this->savino, 1, 30);

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('standings', 2)
            ->where('standings.0.pos', 1)
            ->where('standings.0.team', 'Savino Del Bene Volley')
            ->where('standings.0.isOwn', true)
            ->where('standings.0.pts', 30)
            ->where('standings.1.team', 'Il Bisonte Firenze')
            ->where('standings.1.isOwn', false)
        );
    }

    #[Test]
    public function una_gara_non_giocata_non_ha_ne_punteggio_ne_esito(): void
    {
        // Era il difetto più visibile: i punteggi nulli finivano in un confronto
        // fra trattini, quindi ogni gara futura veniva mostrata come sconfitta.
        $this->game();

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('games', 1)
            ->where('games.0.played', false)
            ->where('games.0.result', null)
            ->where('games.0.scoreHome', null)
            ->where('games.0.scoreAway', null)
        );
    }

    #[Test]
    public function una_vittoria_in_trasferta_e_riconosciuta_come_vittoria(): void
    {
        $this->game([
            'home_team_id' => $this->rival->id,
            'away_team_id' => $this->savino->id,
            'match_date' => now()->subWeek(),
            'status' => GameStatus::Completed,
            'home_score' => 1,
            'away_score' => 3,
        ]);

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('games.0.result', 'win')
            ->where('games.0.played', true)
            ->where('games.0.homeIsOwn', false)
            ->where('games.0.awayIsOwn', true)
        );
    }

    #[Test]
    public function una_sconfitta_in_casa_e_riconosciuta_come_sconfitta(): void
    {
        $this->game([
            'match_date' => now()->subWeek(),
            'status' => GameStatus::Completed,
            'home_score' => 2,
            'away_score' => 3,
        ]);

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('games.0.result', 'loss'));
    }

    #[Test]
    public function le_gare_giocate_precedono_quelle_in_calendario(): void
    {
        $this->game(['match_date' => now()->addMonth()]);
        $this->game([
            'match_date' => now()->subMonth(),
            'status' => GameStatus::Completed,
            'home_score' => 3,
            'away_score' => 0,
        ]);

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('games', 2)
            ->where('games.0.played', true)
            ->where('games.1.played', false)
        );
    }

    #[Test]
    public function espone_giornata_e_fase_quando_disponibili(): void
    {
        $this->game(['matchday' => 3, 'phase' => 'Ritorno']);

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('games.0.matchday', 3)
            ->where('games.0.matchdayLabel', '3ª Giornata · Ritorno')
        );
    }

    #[Test]
    public function mostra_tutto_il_campionato_segnalando_le_gare_della_societa(): void
    {
        // Il tifoso segue anche i risultati delle avversarie, che decidono la
        // classifica: si pubblica il campionato per intero e si marcano le
        // nostre gare con `isOwn`, che alimenta evidenza e filtro.
        $other = Team::create(['name' => 'Numia Vero Volley Milano', 'slug' => 'numia', 'is_internal' => false]);

        $this->game(['matchday' => 1]);
        $this->game(['home_team_id' => $other->id, 'away_team_id' => $this->rival->id, 'matchday' => 2]);

        $response = $this->get(route('stagione.risultati'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('games', 2)
            // Ordine di calendario: prima la giornata 1, poi la 2.
            ->where('games.0.isOwn', true)
            ->where('games.1.isOwn', false)
        );

        $response->assertSee('Numia Vero Volley Milano');
    }

    #[Test]
    public function aggiornare_la_classifica_invalida_la_cache_della_pagina(): void
    {
        // La pagina è cachata 5 minuti: senza invalidazione, dopo una
        // sincronizzazione il pubblico continuerebbe a vedere i punti vecchi.
        $standing = $this->standing($this->savino, 1, 30);

        $this->get(route('stagione.risultati'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('standings.0.pts', 30));

        $standing->update(['points' => 33]);

        $this->get(route('stagione.risultati'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('standings.0.pts', 33));
    }

    #[Test]
    public function la_coppa_italia_non_ha_classifica(): void
    {
        $this->standing($this->savino, 1, 30);

        $response = $this->get(route('stagione.coppa-italia'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showStandings', false)
            ->where('standings', [])
        );
    }

    #[Test]
    public function ogni_competizione_mostra_solo_le_proprie_gare(): void
    {
        $this->game(['competition_type' => CompetitionType::Championship]);
        $this->game(['competition_type' => CompetitionType::CoppaItalia]);

        $this->get(route('stagione.risultati'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('games', 1));

        Cache::flush();

        $this->get(route('stagione.coppa-italia'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('games', 1));
    }
}
