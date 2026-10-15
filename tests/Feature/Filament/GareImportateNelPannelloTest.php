<?php

namespace Tests\Feature\Filament;

use App\Enums\CompetitionType;
use App\Enums\UserRole;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\Pages\ViewGame;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le gare importate dalla Lega e dalla CEV si riallineano a ogni
 * sincronizzazione: il pannello lo dice prima che la redazione le modifichi,
 * e la scheda dice da dove vengono.
 */
class GareImportateNelPannelloTest extends TestCase
{
    use RefreshDatabase;

    private User $redattore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redattore = User::factory()->create();
        $this->redattore->forceFill(['role' => UserRole::SuperAdmin])->save();
    }

    private function gara(array $attributi = []): Game
    {
        return Game::factory()->create(array_merge([
            'season_id' => Season::factory()->create()->id,
            'home_team_id' => Team::factory()->create()->id,
            'away_team_id' => Team::factory()->create()->id,
        ], $attributi));
    }

    #[Test]
    public function il_modulo_avvisa_che_una_gara_importata_si_riallinea(): void
    {
        $cev = $this->gara(['cev_match_id' => 87417, 'competition_type' => CompetitionType::ChampionsLeague]);
        $lega = $this->gara(['lvf_match_id' => 3001]);

        Livewire::actingAs($this->redattore)->test(EditGame::class, ['record' => $cev->getRouteKey()])
            ->assertSee('arrivano dalla CEV e vengono riallineati a ogni sincronizzazione');

        Livewire::actingAs($this->redattore)->test(EditGame::class, ['record' => $lega->getRouteKey()])
            ->assertSee('arrivano dalla Lega e vengono riallineati a ogni sincronizzazione');
    }

    #[Test]
    public function una_gara_inserita_a_mano_non_ha_l_avviso(): void
    {
        $manuale = $this->gara();

        Livewire::actingAs($this->redattore)->test(EditGame::class, ['record' => $manuale->getRouteKey()])
            ->assertDontSee('vengono riallineati a ogni sincronizzazione');
    }

    #[Test]
    public function i_biglietti_si_scrivono_solo_sulle_gare_in_casa(): void
    {
        $inCasa = $this->gara([
            'lvf_match_id' => 3002,
            'home_team_id' => Team::factory()->create(['is_internal' => true])->id,
        ]);
        $fuori = $this->gara();

        Livewire::actingAs($this->redattore)->test(EditGame::class, ['record' => $inCasa->getRouteKey()])
            ->assertSee('Link di vendita (Vivaticket)')
            ->fillForm([
                'biglietti_url' => 'https://www.vivaticket.com/it/ticket/savino-novara/1',
                'biglietti_prezzo_da' => '15',
                'biglietti_esauriti' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $inCasa->refresh();
        $this->assertSame('https://www.vivaticket.com/it/ticket/savino-novara/1', $inCasa->biglietti_url);
        $this->assertSame('15.00', $inCasa->biglietti_prezzo_da);
        $this->assertTrue($inCasa->biglietti_esauriti);

        Livewire::actingAs($this->redattore)->test(EditGame::class, ['record' => $fuori->getRouteKey()])
            ->assertDontSee('Link di vendita (Vivaticket)');
    }

    #[Test]
    public function la_scheda_di_una_gara_cev_dice_da_dove_viene(): void
    {
        $cev = $this->gara(['cev_match_id' => 87417, 'competition_type' => CompetitionType::ChampionsLeague]);

        $this->assertTrue($cev->isImported());
        $this->assertSame('https://www-old.cev.eu/Competition-Area/MatchPage.aspx?mID=87417', $cev->indirizzoSulPortaleCev());

        Livewire::actingAs($this->redattore)->test(ViewGame::class, ['record' => $cev->getRouteKey()])
            ->assertSee('Sincronizzazione CEV')
            ->assertSee('87417')
            ->assertDontSee('Sincronizzazione Lega');
    }
}
