<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\NewsletterSubscriberResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PlayerResource;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filament considera permessa ogni azione priva di metodo corrispondente
 * nella policy (o priva di policy). Questi test bloccano le regressioni sui
 * varchi che ne derivavano.
 */
class PanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ])->save();

        return $user->fresh();
    }

    private function actingInPanel(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::auth()->setUser($user);
    }

    public function test_la_cancellazione_in_blocco_non_e_permessa_a_chi_non_puo_cancellare(): void
    {
        // Il Resp. Comunicazione vede le atlete (canViewSport) ma non le gestisce.
        $this->actingInPanel($this->userWithRole(UserRole::CommunicationManager));

        $this->assertFalse(PlayerResource::canDeleteAny());
        $this->assertFalse(PlayerResource::canRestoreAny());
    }

    public function test_gli_ordini_non_sono_cancellabili_in_blocco_dal_resp_shop(): void
    {
        $this->actingInPanel($this->userWithRole(UserRole::ShopManager));

        $this->assertTrue(OrderResource::canViewAny());
        $this->assertFalse(OrderResource::canDeleteAny());
    }

    public function test_solo_il_super_admin_cancella_in_blocco_gli_utenti(): void
    {
        $this->actingInPanel($this->userWithRole(UserRole::SportCoordinator));
        $this->assertFalse(UserResource::canDeleteAny());

        $this->actingInPanel($this->userWithRole(UserRole::SuperAdmin));
        $this->assertTrue(UserResource::canDeleteAny());
    }

    public function test_il_registro_attivita_e_riservato_al_super_admin(): void
    {
        $this->actingInPanel($this->userWithRole(UserRole::ShopManager));
        $this->assertFalse(ActivityLogResource::canViewAny());

        $this->actingInPanel($this->userWithRole(UserRole::SuperAdmin));
        $this->assertTrue(ActivityLogResource::canViewAny());
    }

    public function test_gli_iscritti_newsletter_sono_riservati_alla_comunicazione(): void
    {
        $this->actingInPanel($this->userWithRole(UserRole::SportCoordinator));
        $this->assertFalse(NewsletterSubscriberResource::canViewAny());

        $this->actingInPanel($this->userWithRole(UserRole::CommunicationManager));
        $this->assertTrue(NewsletterSubscriberResource::canViewAny());
    }
}
