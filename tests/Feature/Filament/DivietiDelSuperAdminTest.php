<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Il bypass di `Gate::before` dà tutto al super admin, ma alcuni divieti sono
 * di principio e non di ruolo: gli ordini non si cancellano (conservazione
 * fiscale), le righe di un ordine pagato non si toccano, nessuno cancella sé
 * stesso. Quelli devono valere anche per lui.
 */
class DivietiDelSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->superAdmin();
        $this->actingAs($this->admin);
    }

    private function superAdmin(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $utente->refresh();
    }

    public function test_il_super_admin_non_cancella_gli_ordini(): void
    {
        $ordine = Order::factory()->create();
        $gate = Gate::forUser($this->admin);

        $this->assertFalse($gate->allows('delete', $ordine));
        $this->assertFalse($gate->allows('deleteAny', Order::class));
        $this->assertFalse($gate->allows('forceDelete', $ordine));
        $this->assertFalse($gate->allows('forceDeleteAny', Order::class));

        // Il resto degli ordini resta suo.
        $this->assertTrue($gate->allows('update', $ordine));
        $this->assertTrue($gate->allows('viewAny', Order::class));
    }

    public function test_il_super_admin_non_modifica_le_righe_di_un_ordine_pagato(): void
    {
        $pagato = Order::factory()->paid()->create();
        $riga = OrderItem::factory()->create(['order_id' => $pagato->id]);
        $gate = Gate::forUser($this->admin);

        $this->assertFalse($gate->allows('update', $riga));
        $this->assertFalse($gate->allows('delete', $riga));
        $this->assertFalse($gate->allows('deleteAny', OrderItem::class));
    }

    public function test_il_super_admin_non_cancella_se_stesso_ma_gli_altri_si(): void
    {
        $altro = User::factory()->create();
        $gate = Gate::forUser($this->admin);

        $this->assertFalse($gate->allows('delete', $this->admin));
        $this->assertTrue($gate->allows('delete', $altro));
        // Il bypass resta per tutto il resto.
        $this->assertTrue($gate->allows('deleteAny', Player::class));
    }

    public function test_il_pannello_non_offre_la_cancellazione_degli_ordini(): void
    {
        $ordine = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->assertTableBulkActionDoesNotExist('delete');

        Livewire::test(EditOrder::class, ['record' => $ordine->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_la_cancellazione_in_blocco_degli_utenti_salta_se_stessi(): void
    {
        $altro = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('delete', [$this->admin, $altro]);

        $this->assertModelExists($this->admin);
        $this->assertModelMissing($altro);
    }

    public function test_l_ultimo_super_admin_attivo_non_si_cancella(): void
    {
        // Un secondo super admin disattivato: chi resta attivo è uno solo.
        $disattivato = $this->superAdmin();
        $disattivato->forceFill(['is_active' => false])->save();

        $this->assertFalse(Gate::forUser($disattivato)->allows('delete', $this->admin));
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $disattivato));
    }
}
