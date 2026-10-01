<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

/**
 * «Password dimenticata» del pannello.
 *
 * Filament, inviato il link, resta sul modulo vuoto con una notifica a
 * comparsa che sparisce in pochi secondi: sembrava che non fosse successo
 * niente. Qui il modulo lascia il posto a una conferma che resta a schermo.
 *
 * La conferma è la stessa anche quando l'indirizzo non è di nessuno, come nel
 * reset dello shop (`PasswordResetLinkController`): la notifica d'errore di
 * Filament («Non troviamo nessun utente…») permetteva di scoprire quali email
 * hanno un account nel pannello.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    /** @var view-string */
    protected static string $view = 'filament.pages.auth.request-password-reset';

    public ?string $inviataA = null;

    private bool $limiteRaggiunto = false;

    public function request(): void
    {
        $email = $this->form->getState()['email'] ?? null;

        parent::request();

        // Il limite di Filament (due richieste al minuto) avvisa con la sua
        // notifica e lascia il modulo: lì l'utente deve poter riprovare.
        if ($this->getErrorBag()->isEmpty() && ! $this->limiteRaggiunto) {
            $this->inviataA = is_string($email) ? $email : null;
        }
    }

    public function altroIndirizzo(): void
    {
        $this->inviataA = null;
        $this->form->fill();
    }

    public function minutiDiValidita(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $this->limiteRaggiunto = true;

        return parent::getRateLimitedNotification($exception);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return null;
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return null;
    }
}
