<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\PasswordExpiringSoon;
use Illuminate\Auth\Events\Login;

/**
 * Al login, se la password sta per scadere, manda il preavviso via email.
 *
 * L'invio avviene una sola volta per finestra di preavviso: `password_changed_at`
 * viene riscritto a ogni cambio password e azzera `password_expiry_notified_at`,
 * quindi il ciclo successivo tornerà ad avvisare.
 */
class NotifyPasswordExpiringSoon
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->passwordIsExpiringSoon()) {
            return;
        }

        if ($user->password_expiry_notified_at !== null) {
            return;
        }

        $user->forceFill(['password_expiry_notified_at' => now()])->saveQuietly();

        $user->notify(new PasswordExpiringSoon(
            (int) $user->daysUntilPasswordExpires(),
            $user->passwordExpiresAt()?->toDateString(),
        ));
    }
}
