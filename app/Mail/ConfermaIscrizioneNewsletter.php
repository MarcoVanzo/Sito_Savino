<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La richiesta di conferma dell'iscrizione alla newsletter (doppio opt-in).
 *
 * È l'unica prova che chi ha scritto l'indirizzo nel modulo sia chi legge la
 * casella: senza, chiunque poteva iscrivere chiunque. Finché il link non viene
 * cliccato l'indirizzo non esce dal sito (SyncNewsletterToActiveCampaign).
 */
class ConfermaIscrizioneNewsletter extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public string $lingua = 'it',
    ) {
        // Parte da un worker: la lingua è quella della pagina da cui è
        // arrivata la richiesta, non quella del processo.
        $this->locale($lingua);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.newsletter_conferma.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-conferma',
            with: [
                'confermaUrl' => $this->subscriber->confermaUrl($this->lingua),
            ],
        );
    }
}
