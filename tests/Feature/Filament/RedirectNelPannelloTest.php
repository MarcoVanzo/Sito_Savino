<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\IndirizzoDaMappareResource\Pages\ListIndirizziDaMappare;
use App\Filament\Resources\ReindirizzamentoResource\Pages\CreateReindirizzamento;
use App\Filament\Resources\ReindirizzamentoResource\Pages\ListReindirizzamenti;
use App\Models\IndirizzoDaMappare;
use App\Models\Reindirizzamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RedirectNelPannelloTest extends TestCase
{
    use RefreshDatabase;

    private function utente(UserRole $ruolo): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $ruolo, 'is_active' => true])->save();

        return $user->refresh();
    }

    #[Test]
    public function l_amministratore_crea_un_redirect(): void
    {
        $this->actingAs($this->utente(UserRole::SuperAdmin));

        Livewire::test(ListReindirizzamenti::class)->assertSuccessful();

        Livewire::test(CreateReindirizzamento::class)
            ->fillForm(['from_path' => '/Tag/Scudetto/', 'status_code' => '301', 'to_path' => '/stagione/playoff'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', ['from_path' => '/tag/scudetto', 'to_path' => '/stagione/playoff']);
    }

    #[Test]
    public function il_modulo_rifiuta_i_redirect_sbagliati(): void
    {
        $this->actingAs($this->utente(UserRole::SuperAdmin));
        Reindirizzamento::create(['from_path' => '/gia-mappato', 'to_path' => '/news', 'status_code' => '301']);

        Livewire::test(CreateReindirizzamento::class)
            ->fillForm(['from_path' => '/una-pagina', 'status_code' => '301', 'to_path' => ''])
            ->call('create')
            ->assertHasFormErrors(['to_path']);

        Livewire::test(CreateReindirizzamento::class)
            ->fillForm(['from_path' => '/una-pagina/', 'status_code' => '301', 'to_path' => '/una-pagina'])
            ->call('create')
            ->assertHasFormErrors(['to_path']);

        Livewire::test(CreateReindirizzamento::class)
            ->fillForm(['from_path' => '/una-pagina', 'status_code' => '301', 'to_path' => 'http://altro.it/x'])
            ->call('create')
            ->assertHasFormErrors(['to_path']);

        Livewire::test(CreateReindirizzamento::class)
            ->fillForm(['from_path' => '/GIA-MAPPATO/', 'status_code' => '301', 'to_path' => '/stagione'])
            ->call('create')
            ->assertHasFormErrors(['from_path']);

        Livewire::test(CreateReindirizzamento::class)
            ->fillForm(['from_path' => '/pagina-tolta', 'status_code' => '410'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('redirects', ['from_path' => '/pagina-tolta', 'to_path' => null, 'status_code' => '410']);
        $this->assertDatabaseCount('redirects', 2);
    }

    #[Test]
    public function dai_404_da_mappare_si_crea_il_redirect(): void
    {
        $this->actingAs($this->utente(UserRole::SuperAdmin));
        $indirizzo = IndirizzoDaMappare::create([
            'percorso' => '/una-notizia-del-2017',
            'origine' => IndirizzoDaMappare::ORIGINE_404,
            'hits' => 7,
            'last_hit_at' => now(),
        ]);

        Livewire::test(ListIndirizziDaMappare::class)
            ->assertCanSeeTableRecords([$indirizzo])
            ->callTableAction('creaRedirect', $indirizzo, ['to_path' => '/news'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('redirects', ['from_path' => '/una-notizia-del-2017', 'to_path' => '/news']);
        $this->assertDatabaseCount('redirect_mancanti', 0);
    }

    #[Test]
    public function la_redazione_non_vede_i_redirect(): void
    {
        $this->actingAs($this->utente(UserRole::CommunicationManager));

        $this->get(ListReindirizzamenti::getUrl())->assertForbidden();
        $this->get(ListIndirizziDaMappare::getUrl())->assertForbidden();
    }
}
