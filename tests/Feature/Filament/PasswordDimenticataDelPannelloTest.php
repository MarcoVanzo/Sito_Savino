<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\ResetPassword as NotificaDiReset;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Tests\TestCase;

/**
 * Il primo argomento di `passwordReset()` è la pagina di richiesta, non quella
 * di reimpostazione. Passando la nostra ResetPassword come unico argomento,
 * «Password dimenticata» mostrava il modulo del link dell'email con il campo
 * email disabilitato: nessuno poteva chiedere il link, e il link vero usava la
 * pagina di Filament senza la policy delle password.
 */
class PasswordDimenticataDelPannelloTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_due_pagine_sono_al_loro_posto(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(RequestPasswordReset::class, $panel->getRequestPasswordResetRouteAction());
        $this->assertSame(ResetPassword::class, $panel->getResetPasswordRouteAction());
    }

    public function test_la_richiesta_del_link_chiede_solo_l_email(): void
    {
        $this->get('/admin/password-reset/request')
            ->assertOk()
            ->assertSee(app(ComponentRegistry::class)->getName(RequestPasswordReset::class), false)
            ->assertDontSee('passwordConfirmation');
    }

    public function test_il_link_arriva_e_porta_alla_pagina_con_la_policy(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertHasNoFormErrors();

        $url = null;
        Notification::assertSentTo($user, NotificaDiReset::class, function (NotificaDiReset $notifica) use (&$url) {
            $url = $notifica->url;

            return true;
        });

        $this->get($url)
            ->assertOk()
            ->assertSee(app(ComponentRegistry::class)->getName(ResetPassword::class), false);
    }
}
