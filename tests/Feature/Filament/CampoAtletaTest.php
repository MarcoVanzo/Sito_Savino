<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Forms\CampoAtleta;
use App\Filament\Resources\RosterResource\Pages\CreateRoster;
use App\Filament\Resources\YouthRosterResource\Pages\CreateYouthRoster;
use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il campo Atleta dei roster ({@see CampoAtleta}).
 */
class CampoAtletaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();
        $this->actingAs($user);
    }

    #[Test]
    public function l_elenco_propone_il_vivaio_e_la_ricerca_trova_anche_la_prima_squadra(): void
    {
        $primaSquadra = Player::factory()->create(['first_name' => 'Anna', 'last_name' => 'Prima']);
        Roster::factory()->for($primaSquadra)->for(Team::factory()->state(['category' => 'A1', 'is_internal' => true]))->create();
        $vivaio = Player::factory()->create();
        Roster::factory()->for($vivaio)->for(Team::factory()->state(['category' => 'U17', 'is_internal' => true]))->create();
        $senzaSquadra = Player::factory()->create(['first_name' => 'Giulia', 'last_name' => 'Nuova']);

        Livewire::test(CreateYouthRoster::class)
            ->assertFormFieldExists('player_id', function (Select $field) use ($primaSquadra, $vivaio, $senzaSquadra): bool {
                $opzioni = $field->getOptions();
                $trovate = $field->getSearchResults('anna prima');

                return ! array_key_exists($primaSquadra->getKey(), $opzioni)
                    && array_key_exists($vivaio->getKey(), $opzioni)
                    && ($opzioni[$senzaSquadra->getKey()] ?? null) === 'Nuova Giulia'
                    && ($trovate[$primaSquadra->getKey()] ?? null) === 'Prima Anna · prima squadra'
                    && $field->getOptionLabel() === null;
            });
    }

    #[Test]
    public function si_crea_un_atleta_nuova_e_si_salva_il_roster(): void
    {
        $squadra = Team::factory()->create(['category' => 'U17', 'is_internal' => true]);
        $stagione = Season::factory()->create();

        $pagina = Livewire::test(CreateYouthRoster::class)
            ->callFormComponentAction('player_id', 'createOption', data: [
                'last_name' => ' Rossi ',
                'first_name' => 'Anna',
            ])
            ->assertHasNoFormComponentActionErrors();

        $atleta = Player::query()->where('last_name', 'Rossi')->where('first_name', 'Anna')->firstOrFail();
        $pagina->assertFormSet(['player_id' => $atleta->getKey()])
            ->fillForm(['team_id' => $squadra->getKey(), 'season_id' => $stagione->getKey(), 'role' => 'libero', 'is_captain' => false])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rosters', ['player_id' => $atleta->getKey(), 'team_id' => $squadra->getKey()]);
    }

    #[Test]
    public function non_si_crea_un_doppione(): void
    {
        Player::factory()->create(['first_name' => 'Anna', 'last_name' => 'Rossi']);

        Livewire::test(CreateYouthRoster::class)
            ->callFormComponentAction('player_id', 'createOption', data: [
                'last_name' => 'rossi',
                'first_name' => 'anna',
            ])
            ->assertHasFormComponentActionErrors(['last_name']);

        $this->assertSame(1, Player::query()->where('last_name', 'Rossi')->count());
    }

    #[Test]
    public function anche_la_prima_squadra_crea_le_atlete(): void
    {
        Livewire::test(CreateRoster::class)
            ->callFormComponentAction('player_id', 'createOption', data: [
                'last_name' => 'Bianchi',
                'first_name' => 'Sara',
            ])
            ->assertHasNoFormComponentActionErrors();

        $this->assertDatabaseHas('players', ['first_name' => 'Sara', 'last_name' => 'Bianchi']);
    }
}
