<?php

namespace Tests\Feature;

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Under 17 e Under 15 hanno una pagina ciascuna, come la B1 / U19: prima
 * l'unica voce "Serie U17 & U15" portava alla pagina "in costruzione".
 *
 * La squadra si sceglie per categoria (`teams.category`), non per slug: il
 * nome cambia con il campionato ed e' la redazione a scriverlo.
 */
class PagineDelleSquadreYouthTest extends TestCase
{
    use RefreshDatabase;

    private Season $stagione;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();

        $this->stagione = Season::factory()->create(['is_current' => true]);
    }

    public function test_ogni_squadra_del_vivaio_pubblica_la_propria_rosa(): void
    {
        $u17 = $this->atletaDi('U17', 'Bianchi');
        $u15 = $this->atletaDi('U15', 'Rossi');

        $this->get('/stagione/u17')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Public/Stagione')
                ->where('teamLabel', 'Serie C / Under 17')
                ->where('teamInfo.name', 'Serie C / Under 17')
                ->where('roster.0.player.last_name', $u17->last_name)
                ->has('roster', 1));

        $this->get('/stagione/u15')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('teamLabel', 'Seconda Divisione / Under 15')
                ->where('roster.0.player.last_name', $u15->last_name)
                ->has('roster', 1));
    }

    /**
     * Il palmares resta alla prima squadra: le voci di Wikipedia delle
     * giovanili non esistono.
     */
    public function test_le_giovanili_non_espongono_il_palmares(): void
    {
        $this->atletaDi('U17', 'Bianchi');

        $this->get('/stagione/u17')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('palmaresEnabled', false));
    }

    /**
     * Le due squadre nascono dalla migrazione, quindi la pagina esiste da
     * subito: senza atlete tesserate mostra la rosa vuota invece di un 500 su
     * una voce di menu pubblicata.
     */
    public function test_senza_atlete_la_pagina_risponde_con_la_rosa_vuota(): void
    {
        $this->get('/stagione/u15')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('roster', [])
                ->where('teamInfo.name', 'Seconda Divisione / Under 15'));
    }

    /**
     * La squadra e' quella creata dalla migrazione: il test usa l'archivio
     * vero, non una squadra inventata che finirebbe in doppione sulla stessa
     * categoria.
     */
    private function atletaDi(string $categoria, string $cognome): Player
    {
        $squadra = Team::where('category', $categoria)->where('is_internal', true)->firstOrFail();

        $atleta = Player::factory()->create(['last_name' => $cognome]);

        Roster::factory()->create([
            'player_id' => $atleta->id,
            'team_id' => $squadra->id,
            'season_id' => $this->stagione->id,
            'jersey_number' => 7,
            'role' => PlayerPosition::Setter,
        ]);

        return $atleta;
    }
}
