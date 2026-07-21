<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Preavviso di scadenza password.
 *
 * Inviata una sola volta per finestra di preavviso (vedi
 * `User::password_expiry_notified_at`). Finché MAIL_MAILER resta su `log` il
 * messaggio viene solo scritto nei log: si attiverà da sé quando verrà
 * configurato il mailer di produzione.
 */
class PasswordExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $days,
        private readonly ?string $expiresOn,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quando = $this->days <= 1
            ? 'entro oggi'
            : "fra {$this->days} giorni";

        $message = (new MailMessage)
            ->subject('La tua password sta per scadere')
            ->greeting('Ciao '.($notifiable->name ?? '').'!')
            ->line("Per motivi di sicurezza la password del tuo account scade {$quando}.");

        if ($this->expiresOn !== null) {
            $message->line("Data di scadenza: {$this->expiresOn}.");
        }

        return $message
            ->line('Cambiandola ora eviti di trovarti bloccato al prossimo accesso.')
            ->action('Cambia password', url(route('password.change', absolute: false)))
            ->line('Se hai già cambiato la password di recente, puoi ignorare questo messaggio.');
    }
}
