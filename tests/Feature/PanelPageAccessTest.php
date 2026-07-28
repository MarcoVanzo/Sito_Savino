<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\MagazzinoPage;
use App\Filament\Pages\OfficialPhotoPage;
use App\Filament\Pages\ShopAnalyticsPage;
use App\Filament\Pages\SizeGuideContactsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chi vede le pagine autonome del pannello.
 *
 * Le pagine di Filament non hanno una policy: senza `canAccess()` sono visibili
 * a chiunque entri nel pannello. Il controllo è ora centralizzato in un trait, e
 * questo test fissa il risultato ruolo per ruolo perché la centralizzazione non
 * possa allargare i permessi in silenzio.
 */
class PanelPageAccessTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        // `role` non è mass-assignable: la factory lo scarterebbe.
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();

        $this->actingAs($user->refresh());

        return $user;
    }

    /**
     * @return array<string, array{class-string, UserRole, bool}>
     */
    public static function accessi(): array
    {
        $casi = [];

        $matrice = [
            'magazzino' => [MagazzinoPage::class, [
                UserRole::SuperAdmin->value => true,
                UserRole::ShopManager->value => true,
                UserRole::CommunicationManager->value => false,
                UserRole::SportCoordinator->value => false,
                UserRole::User->value => false,
                UserRole::Customer->value => false,
            ]],
            'analytics shop' => [ShopAnalyticsPage::class, [
                UserRole::SuperAdmin->value => true,
                UserRole::ShopManager->value => true,
                UserRole::CommunicationManager->value => false,
                UserRole::SportCoordinator->value => false,
                UserRole::User->value => false,
                UserRole::Customer->value => false,
            ]],
            'guida taglie' => [SizeGuideContactsPage::class, [
                UserRole::SuperAdmin->value => true,
                UserRole::ShopManager->value => true,
                UserRole::CommunicationManager->value => false,
                UserRole::SportCoordinator->value => false,
                UserRole::User->value => false,
                UserRole::Customer->value => false,
            ]],
            'foto ufficiale' => [OfficialPhotoPage::class, [
                UserRole::SuperAdmin->value => true,
                UserRole::SportCoordinator->value => true,
                UserRole::ShopManager->value => false,
                UserRole::CommunicationManager->value => false,
                UserRole::User->value => false,
                UserRole::Customer->value => false,
            ]],
        ];

        foreach ($matrice as $pagina => [$class, $ruoli]) {
            foreach ($ruoli as $ruolo => $atteso) {
                $casi["{$pagina} / {$ruolo}"] = [$class, UserRole::from($ruolo), $atteso];
            }
        }

        return $casi;
    }

    /**
     * @param  class-string  $page
     */
    #[Test]
    #[DataProvider('accessi')]
    public function laccesso_alle_pagine_rispetta_il_ruolo(string $page, UserRole $role, bool $atteso): void
    {
        $this->actingAsRole($role);

        $this->assertSame($atteso, $page::canAccess());
    }

    #[Test]
    public function un_visitatore_non_autenticato_non_accede_a_nessuna_pagina(): void
    {
        foreach ([MagazzinoPage::class, ShopAnalyticsPage::class, SizeGuideContactsPage::class, OfficialPhotoPage::class] as $page) {
            $this->assertFalse($page::canAccess(), $page.' è accessibile senza autenticazione.');
        }
    }
}
