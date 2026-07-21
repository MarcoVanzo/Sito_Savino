<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Rules\NotAPreviousPassword;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Reset password del pannello CMS.
 *
 * Estende la pagina di Filament per applicare la stessa policy del resto
 * dell'applicazione: requisiti di robustezza (Password::defaults) e divieto di
 * riuso delle ultime N password.
 */
class ResetPassword extends BaseResetPassword
{
    protected function getPasswordFormComponent(): TextInput
    {
        return parent::getPasswordFormComponent()
            ->rule(PasswordRule::defaults())
            ->rule(new NotAPreviousPassword($this->resolveUser()));
    }

    /**
     * L'utente destinatario del reset, ma solo se il token è valido: la
     * validazione gira prima della verifica del token, e applicare il divieto
     * di riuso a chiunque permetterebbe di scoprire senza token se una certa
     * password è fra le ultime usate da quell'indirizzo.
     */
    private function resolveUser(): ?User
    {
        $email = is_string($this->email ?? null) ? $this->email : null;
        $token = is_string($this->token ?? null) ? $this->token : null;

        if ($email === null || $token === null) {
            return null;
        }

        $user = User::where('email', $email)->first();

        if ($user === null || ! Password::getRepository()->exists($user, $token)) {
            return null;
        }

        return $user;
    }
}
