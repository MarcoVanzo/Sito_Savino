<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Statistiche aggregate della rosa sulla pagina pubblica della stagione.
 *
 * I totali arrivano dai tabellini della Lega e servono a confrontare le atlete
 * fra loro: questi test fissano cosa finisce in pagina e, soprattutto, cosa
 * NON deve finirci quando il dato non c'è.
 */
class SeasonStatsPageTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::create(['name' => '2026/2027', 'is_current' => true]);
        $this->team = Team::create([
            'name' => 'Savino Del Bene Volley',
            'slug' => 'savino-del-bene-volley',
            'is_internal' => true,
        ]);
    }

    private function player(string $first, string $last, int $jersey, array $stats = []): Player
    {
        $player = Player::create(['first_name' => $first, 'last_name' => $last]);

        Roster::create([
            'player_id' => $player->id,
            'team_id' => $this->team->id,
            'season_id' => $this->season->id,
            'jersey_number' => $jersey,
        ]);

        if ($stats !== []) {
            PlayerStat::create(array_merge([
                'player_id' => $player->id,
                'season_id' => $this->season->id,
                'team_id' => $this->team->id,
            ], $stats));
        }

        return $player;
    }

    #[Test]
    public function mostra_i_totali_di_stagione_della_rosa(): void
    {
        $this->player('Emma', 'Graziani', 7, [
            'matches_played' => 4,
            'sets_played' => 16,
            'points' => 60,
            'blocks' => 9,
            'aces' => 5,
            'attacks' => 150,
            'attack_points' => 60,
            'attack_pct' => 40,
            'receptions' => 20,
            'reception_positive_pct' => 55,
            'last_synced_at' => now(),
        ]);

        $this->get(route('stagione'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seasonStats', 1)
            ->where('seasonStats.0.points', 60)
            ->where('seasonStats.0.matchesPlayed', 4)
            ->where('seasonStats.0.setsPlayed', 16)
            // 60 punti in 16 set: il confronto fra atlete con presenze diverse
            // si fa su questo, non sui punti assoluti.
            ->where('seasonStats.0.pointsPerSet', 3.75)
            ->where('seasonStats.0.attackPoints', 60)
            ->where('seasonStats.0.attackPct', 40)
            ->where('seasonStats.0.blocks', 9)
        );
    }

    #[Test]
    public function senza_tabellini_non_mostra_una_tabella_di_zeri(): void
    {
        // È il caso delle giovanili e di una stagione appena iniziata: una
        // griglia di zeri direbbe che le atlete hanno giocato senza segnare.
        $this->player('Maja', 'Ognjenovic', 1);
        $this->player('Avery', 'Skinner', 11);

        $this->get(route('stagione'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('roster', 2)
            ->has('seasonStats', 0)
        );
    }

    #[Test]
    public function i_totali_di_unaltra_squadra_non_finiscono_in_questa_pagina(): void
    {
        // Un'atleta puo' essere schierata anche in B1: i due bilanci sono
        // distinti e questa pagina deve mostrare solo quello della sua squadra.
        $altra = Team::create(['name' => 'Serie B1', 'slug' => 'serie-b1', 'is_internal' => true]);

        $player = $this->player('Emma', 'Graziani', 7, [
            'matches_played' => 2,
            'sets_played' => 8,
            'points' => 30,
            'last_synced_at' => now(),
        ]);

        PlayerStat::create([
            'player_id' => $player->id,
            'season_id' => $this->season->id,
            'team_id' => $altra->id,
            'matches_played' => 9,
            'sets_played' => 30,
            'points' => 999,
            'last_synced_at' => now(),
        ]);

        $this->get(route('stagione'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seasonStats', 1)
            ->where('seasonStats.0.points', 30)
        );
    }

    #[Test]
    public function unatleta_a_referto_ma_mai_entrata_resta_in_tabella_a_zero(): void
    {
        // È un dato vero del tabellino, non un'assenza di dato: va mostrato.
        $this->player('Emma', 'Graziani', 7, [
            'matches_played' => 3, 'sets_played' => 10, 'points' => 40, 'last_synced_at' => now(),
        ]);
        $this->player('Sveva', 'Parini', 9, [
            'matches_played' => 0, 'sets_played' => 0, 'points' => 0, 'last_synced_at' => now(),
        ]);

        $this->get(route('stagione'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seasonStats', 2)
            // Ordinate per punti: chi non è entrata resta in coda.
            ->where('seasonStats.0.points', 40)
            ->where('seasonStats.1.points', 0)
            ->where('seasonStats.1.pointsPerSet', null)
        );
    }
}
