<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La scheda di uno sponsor si apre.
 *
 * Le pagine usavano il trait delle traduzioni, ma il model dichiara
 * `$translatable = []` — un nome di marchio e un indirizzo non si traducono — e
 * il plugin rifiuta un elenco vuoto: cliccando su uno sponsor per modificarlo
 * il pannello rispondeva 500.
 */
class SponsorResourceTest extends TestCase
{
    use RefreshDatabase;

    private function redattore(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $utente->refresh();
    }

    #[Test]
    public function la_scheda_di_uno_sponsor_si_apre(): void
    {
        $this->actingAs($this->redattore());

        $sponsor = Sponsor::create(['name' => 'ESTRA', 'tier' => 'main', 'url' => 'https://www.estra.it/', 'sort_order' => 0]);

        $this->get("/admin/sponsor/{$sponsor->id}/edit")->assertSuccessful();
    }

    /** Uno sponsor senza sito non deve comportarsi diversamente. */
    #[Test]
    public function si_apre_anche_senza_indirizzo(): void
    {
        $this->actingAs($this->redattore());

        $sponsor = Sponsor::create(['name' => 'Senza sito', 'tier' => 'official', 'url' => null, 'sort_order' => 0]);

        $this->get("/admin/sponsor/{$sponsor->id}/edit")->assertSuccessful();
    }

    #[Test]
    public function l_elenco_e_la_creazione_si_aprono(): void
    {
        $this->actingAs($this->redattore());

        Sponsor::create(['name' => 'ESTRA', 'tier' => 'main', 'url' => 'https://www.estra.it/', 'sort_order' => 0]);

        $this->get('/admin/sponsor')->assertSuccessful();
        $this->get('/admin/sponsor/create')->assertSuccessful();
    }
}
