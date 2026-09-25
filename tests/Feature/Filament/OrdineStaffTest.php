<?php

namespace Tests\Feature\Filament;

use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Filament\Resources\ManagementResource\Pages\ListManagement;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Organigramma e staff: un membro nuovo va in fondo, il riordino dal pannello
 * si vede subito sul sito e salvare un membro aggiorna anche /stagione.
 */
class OrdineStaffTest extends TestCase
{
    use RefreshDatabase;

    private function membro(string $nome, StaffType $tipo = StaffType::Dirigenza, int $ordine = 0): StaffMember
    {
        return StaffMember::create([
            'first_name' => $nome,
            'last_name' => $nome,
            'role' => 'Ruolo',
            'type' => $tipo,
            'section' => 'a1',
            'sort_order' => $ordine,
        ]);
    }

    public function test_un_membro_nuovo_va_in_fondo(): void
    {
        $this->membro('Presidente', ordine: 1);
        $this->membro('Scout', StaffType::Tecnico, 15);

        $nuovo = $this->membro('Team Manager');

        $this->assertSame(16, $nuovo->fresh()->sort_order);
    }

    public function test_una_posizione_scelta_resta_quella(): void
    {
        $this->membro('Presidente', ordine: 5);

        $this->assertSame(3, $this->membro('Vice', ordine: 3)->fresh()->sort_order);
    }

    public function test_il_riordino_dal_pannello_butta_la_cache_dell_organigramma(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin])->save();

        $primo = $this->membro('Primo');
        $secondo = $this->membro('Secondo');
        Cache::put('public:organigramma:page:it', ['vecchio'], 1800);

        Livewire::actingAs($admin)
            ->test(ListManagement::class)
            ->call('reorderTable', [(string) $secondo->id, (string) $primo->id])
            ->assertOk();

        $this->assertSame(1, $secondo->fresh()->sort_order);
        $this->assertSame(2, $primo->fresh()->sort_order);
        $this->assertFalse(Cache::has('public:organigramma:page:it'));
    }

    public function test_salvare_un_membro_dello_staff_aggiorna_la_stagione(): void
    {
        Cache::put('public:stagione:it', ['vecchio'], 600);

        $this->membro('Secondo Scout', StaffType::Tecnico);

        $this->assertFalse(Cache::has('public:stagione:it'));
    }

    public function test_il_pulsante_di_riordino_ha_un_etichetta(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin])->save();
        $this->membro('Primo');

        Livewire::actingAs($admin)
            ->test(ListManagement::class)
            ->assertSee('Riordina');
    }
}
