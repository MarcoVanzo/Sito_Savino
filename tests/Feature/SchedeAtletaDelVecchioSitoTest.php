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

    private Season $stagione;

    private Team $squadra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stagione = Season::factory()->create(['is_current' => true]);
        $this->squadra = Team::factory()->create(['slug' => 'savino-del-bene-volley', 'category' => 'A1', 'is_internal' => true]);
        $this->atleta = $this->inRosa('Ekaterina', 'Antropova');
    }

    private function inRosa(string $nome, string $cognome): Player
    {
        $atleta = Player::factory()->create(['first_name' => $nome, 'last_name' => $cognome]);
        Roster::factory()->create(['player_id' => $atleta->id, 'team_id' => $this->squadra->id, 'season_id' => $this->stagione->id]);

        return $atleta;
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

    #[Test]
    public function il_cognome_si_confronta_senza_accenti_e_maiuscole(): void
    {
        $maja = $this->inRosa('Maja', 'Ognjenović');

        $this->get('/giocatrice/Ognjenovic/')
            ->assertStatus(301)
            ->assertRedirect(route('stagione.atleta', ['slug' => $maja->id.'-maja-ognjenovic']));
    }

    #[Test]
    public function due_atlete_con_lo_stesso_cognome_portano_alla_rosa(): void
    {
        $this->inRosa('Anna', 'Antropova');

        $this->get('/giocatrice/antropova/')->assertStatus(301)->assertRedirect(route('stagione'));
    }

    #[Test]
    public function la_pagina_di_un_atleta_uscita_di_rosa_porta_alla_rosa(): void
    {
        $exAtleta = Player::factory()->create(['first_name' => 'Elena', 'last_name' => 'Pietrini']);

        $this->get('/stagione/atleta/'.$exAtleta->id.'-elena-pietrini')
            ->assertStatus(301)
            ->assertRedirect(route('stagione'));
        $this->get('/stagione/atleta/999999-nessuna')->assertNotFound();
    }

    #[Test]
    public function a_inizio_stagione_con_la_rosa_ancora_vuota_il_rimando_e_temporaneo(): void
    {
        $this->stagione->update(['is_current' => false]);
        Season::factory()->create(['is_current' => true]);

        $this->get('/stagione/atleta/'.$this->atleta->id.'-ekaterina-antropova')
            ->assertStatus(302)
            ->assertRedirect(route('stagione'));
    }

    #[Test]
    public function in_inglese_i_rimandi_restano_in_inglese(): void
    {
        $exAtleta = Player::factory()->create(['first_name' => 'Elena', 'last_name' => 'Pietrini']);

        $this->get('/en/stagione/atleta/'.$this->atleta->id.'-katia-antropova')
            ->assertStatus(301)
            ->assertRedirect(route('en.stagione.atleta', ['slug' => $this->atleta->id.'-ekaterina-antropova']));
        $this->get('/en/stagione/atleta/'.$exAtleta->id.'-elena-pietrini')
            ->assertStatus(301)
            ->assertRedirect(route('en.stagione'));
    }

    #[Test]
    public function uno_slug_con_il_nome_scritto_diversamente_porta_a_quello_giusto(): void
    {
        $this->get('/stagione/atleta/'.$this->atleta->id.'-katia-antropova')
            ->assertStatus(301)
            ->assertRedirect(route('stagione.atleta', ['slug' => $this->atleta->id.'-ekaterina-antropova']));
    }
}
