<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/giocatrice/{cognome}/` del vecchio sito porta alla pagina dell'atleta
 * quando il cognome è di una sola atleta della prima squadra di oggi.
 */
class SchedeAtletaDelVecchioSitoTest extends TestCase
{
    use RefreshDatabase;

    private Player $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        $stagione = Season::factory()->create(['is_current' => true]);
        $squadra = Team::factory()->create(['slug' => 'savino-del-bene-volley', 'category' => 'A1', 'is_internal' => true]);
        $this->atleta = Player::factory()->create(['first_name' => 'Ekaterina', 'last_name' => 'Antropova']);
        Roster::factory()->create(['player_id' => $this->atleta->id, 'team_id' => $squadra->id, 'season_id' => $stagione->id]);
    }

    #[Test]
    public function il_cognome_di_un_atleta_in_rosa_porta_alla_sua_pagina(): void
    {
        $this->get('/giocatrice/antropova/')
            ->assertRedirect(route('stagione.atleta', ['slug' => $this->atleta->id.'-ekaterina-antropova']))
            ->assertStatus(301);

        $this->get('/giocatrice/ekaterina-antropova')
            ->assertRedirect(route('stagione.atleta', ['slug' => $this->atleta->id.'-ekaterina-antropova']));
    }

    #[Test]
    public function uno_slug_che_non_e_di_un_atleta_in_rosa_porta_alla_rosa(): void
    {
        $this->get('/giocatrice/daniele-fallani-stefano-campani/')->assertRedirect(route('stagione'));
    }
}
