<?php

namespace Tests\Feature;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La scheda di una singola gara: intestazione, andamento dei set e tabellino.
 *
 * I dati arrivano dal referto della Lega, che ha due abitudini da cui la pagina
 * si deve difendere: esporta sempre cinque set anche quando se ne sono giocati
 * tre, e riempie i parziali con stringhe libere.
 */
class SchedaPartitaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function garaGiocata(array $attributi = []): Game
    {
        $stagione = Season::factory()->create();

        return Game::factory()->create(array_merge([
            'season_id' => $stagione->id,
            'status' => GameStatus::Completed,
            'competition_type' => CompetitionType::Championship,
            'home_score' => 3,
            'away_score' => 1,
            'match_date' => now()->subDays(3),
        ], $attributi));
    }

    private function tabellino(Game $gara, int $teamId, array $attributi = []): GamePlayerStat
    {
        return GamePlayerStat::create(array_merge([
            'game_id' => $gara->id,
            'team_id' => $teamId,
            'player_name' => 'Rossi Maria',
            'jersey_number' => 7,
            'sets_played' => 4,
            'points_total' => 15,
        ], $attributi));
    }

    #[Test]
    public function una_gara_senza_tabellino_non_ha_scheda(): void
    {
        $gara = $this->garaGiocata();

        $this->get(route('stagione.partita', $gara))->assertNotFound();
    }

    #[Test]
    public function una_gara_non_ancora_giocata_non_ha_scheda(): void
    {
        $gara = $this->garaGiocata([
            'status' => GameStatus::Scheduled,
            'home_score' => null,
            'away_score' => null,
        ]);
        $this->tabellino($gara, $gara->home_team_id);

        $this->get(route('stagione.partita', $gara))->assertNotFound();
    }

    #[Test]
    public function la_scheda_mostra_squadre_punteggio_e_impianto(): void
    {
        $gara = $this->garaGiocata(['location' => 'PalaBigmat']);
        $this->tabellino($gara, $gara->home_team_id);

        $this->get(route('stagione.partita', $gara))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Partita')
                ->where('game.home.score', 3)
                ->where('game.away.score', 1)
                ->where('game.location', 'PalaBigmat')
            );
    }

    /**
     * La Lega manda cinque set anche per una gara finita 3-1: gli ultimi
     * arrivano a "0-0" e non vanno mostrati.
     */
    #[Test]
    public function i_set_mai_giocati_non_compaiono(): void
    {
        $gara = $this->garaGiocata([
            'set_scores' => [
                ['set' => 1, 'duration' => 27, 'partials' => ['8-5', '16-12', '25-20']],
                ['set' => 2, 'duration' => 31, 'partials' => ['8-6', '25-22']],
                ['set' => 3, 'duration' => 0, 'partials' => ['0-0']],
                ['set' => 4, 'duration' => 0, 'partials' => []],
            ],
        ]);
        $this->tabellino($gara, $gara->home_team_id);

        $this->get(route('stagione.partita', $gara))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('game.sets', 2)
                ->where('game.sets.0.home', 25)
                ->where('game.sets.0.away', 20)
                ->where('game.sets.0.duration', 27)
                // L'ultimo parziale è il punteggio del set: fra i parziali
                // intermedi restano solo quelli prima.
                ->where('game.sets.0.partials', ['8-5', '16-12'])
                ->where('game.sets.1.home', 25)
            );
    }

    #[Test]
    public function i_parziali_illeggibili_vengono_scartati(): void
    {
        $gara = $this->garaGiocata([
            'set_scores' => [
                ['set' => 1, 'partials' => ['8-5', 'n.d.', '', '25-20']],
                ['set' => 2, 'partials' => ['niente di numerico']],
            ],
        ]);
        $this->tabellino($gara, $gara->home_team_id);

        $this->get(route('stagione.partita', $gara))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('game.sets', 1)
                ->where('game.sets.0.home', 25)
                ->where('game.sets.0.partials', ['8-5'])
            );
    }

    #[Test]
    public function il_tabellino_e_ordinato_per_numero_di_maglia_e_i_senza_numero_stanno_in_fondo(): void
    {
        $gara = $this->garaGiocata();
        $this->tabellino($gara, $gara->home_team_id, ['player_name' => 'Terza', 'jersey_number' => null]);
        $this->tabellino($gara, $gara->home_team_id, ['player_name' => 'Seconda', 'jersey_number' => 12]);
        $this->tabellino($gara, $gara->home_team_id, ['player_name' => 'Prima', 'jersey_number' => 3]);

        $this->get(route('stagione.partita', $gara))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('homeStats', 3)
                ->where('homeStats.0.name', 'Prima')
                ->where('homeStats.1.name', 'Seconda')
                ->where('homeStats.2.name', 'Terza')
            );
    }

    #[Test]
    public function le_due_squadre_hanno_tabellini_separati(): void
    {
        $gara = $this->garaGiocata();
        $this->tabellino($gara, $gara->home_team_id, ['player_name' => 'Di casa']);
        $this->tabellino($gara, $gara->away_team_id, ['player_name' => 'In trasferta']);

        $this->get(route('stagione.partita', $gara))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('homeStats', 1)
                ->has('awayStats', 1)
                ->where('homeStats.0.name', 'Di casa')
                ->where('awayStats.0.name', 'In trasferta')
            );
    }

    /**
     * Le righe delle avversarie non hanno `player_id`: restano nel tabellino
     * ma senza link, perché la scheda atleta non esiste.
     */
    #[Test]
    public function solo_le_nostre_atlete_hanno_il_link_alla_scheda(): void
    {
        $gara = $this->garaGiocata();
        $atleta = Player::factory()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);

        $this->tabellino($gara, $gara->home_team_id, [
            'player_id' => $atleta->id,
            'player_name' => 'Bianchi Anna',
            'jersey_number' => 1,
        ]);
        $this->tabellino($gara, $gara->away_team_id, ['player_name' => 'Avversaria', 'jersey_number' => 1]);

        $this->get(route('stagione.partita', $gara))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('homeStats.0.playerSlug', $atleta->id.'-anna-bianchi')
                ->where('awayStats.0.playerSlug', null)
            );
    }

    #[Test]
    public function la_pagina_classifica_riporta_la_data_dell_ultima_sincronizzazione(): void
    {
        $stagione = Season::factory()->create(['is_current' => true]);
        $squadra = Team::factory()->create(['is_internal' => true]);

        $stagione->standings()->create([
            'team_id' => $squadra->id,
            'competition_type' => CompetitionType::Championship,
            'position' => 1,
            'points' => 42,
            'played' => 15,
            'won' => 14,
            'lost' => 1,
            'synced_at' => now()->subHour(),
        ]);

        $this->get(route('stagione.classifica'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Classifica')
                ->has('standings', 1)
                ->where('standings.0.pts', 42)
                ->where('standings.0.pos', 1)
                ->whereNot('updatedAt', null)
            );
    }

    #[Test]
    public function la_classifica_senza_sincronizzazioni_non_mostra_una_data(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('stagione.classifica'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('updatedAt', null));
    }

    #[Test]
    public function le_altre_competizioni_hanno_la_loro_pagina(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('stagione.cev'))->assertOk();
        $this->get(route('stagione.playoff'))->assertOk();
        $this->get(route('stagione.coppa-italia'))->assertOk();
    }
}
