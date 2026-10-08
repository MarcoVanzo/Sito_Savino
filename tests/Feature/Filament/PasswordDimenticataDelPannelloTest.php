<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\ResetPassword as NotificaDiReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function le_due_pagine_sono_al_loro_posto(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(RequestPasswordReset::class, $panel->getRequestPasswordResetRouteAction());
        $this->assertSame(ResetPassword::class, $panel->getResetPasswordRouteAction());
    }

    #[Test]
    public function la_richiesta_del_link_chiede_solo_l_email(): void
    {
        $this->get('/admin/password-reset/request')
            ->assertOk()
            ->assertSee(app(ComponentRegistry::class)->getName(RequestPasswordReset::class), false)
            ->assertDontSee('passwordConfirmation');
    }

    #[Test]
    public function il_link_arriva_e_porta_alla_pagina_con_la_policy(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertSet('inviataA', $user->email)
            ->assertSee('Controlla la tua email')
            ->assertDontSee('wire:submit="request"', false);

        $url = null;
        Notification::assertSentTo($user, NotificaDiReset::class, function (NotificaDiReset $notifica) use (&$url) {
            $url = $notifica->url;

            return true;
        });

        $this->get($url)
            ->assertOk()
            ->assertSee(app(ComponentRegistry::class)->getName(ResetPassword::class), false);
    }

    /**
     * Stessa conferma per un indirizzo che non ha account: la notifica
     * d'errore di Filament diceva quali email sono registrate nel pannello.
     */
    #[Test]
    public function un_indirizzo_sconosciuto_riceve_la_stessa_conferma(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nessuno@example.com'])
            ->call('request')
            ->assertSet('inviataA', 'nessuno@example.com')
            ->assertSee('Controlla la tua email')
            ->assertDontSee('Non troviamo');

        Notification::assertNothingSent();
    }

    #[Test]
    public function si_puo_tornare_al_modulo_per_un_altro_indirizzo(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'vecchio@example.com'])
            ->call('request')
            ->assertSet('inviataA', 'vecchio@example.com')
            ->call('altroIndirizzo')
            ->assertSet('inviataA', null)
            ->assertFormSet(['email' => null]);
    }

    #[Test]
    public function la_conferma_non_si_accende_dal_browser(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(RequestPasswordReset::class)->set('inviataA', 'chiunque@example.com');
    }

    #[Test]
    public function oltre_il_limite_il_modulo_resta(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $pagina = Livewire::test(RequestPasswordReset::class);

        foreach (['a@example.com', 'b@example.com'] as $email) {
            $pagina->call('altroIndirizzo')->fillForm(['email' => $email])->call('request');
        }

        $pagina->call('altroIndirizzo')
            ->fillForm(['email' => 'c@example.com'])
            ->call('request')
            ->assertSet('inviataA', null);
    }
}
