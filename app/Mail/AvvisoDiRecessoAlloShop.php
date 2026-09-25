<?php

namespace App\Mail;

use App\Models\RichiestaDiRecesso;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avvisa chi gestisce lo shop che e' arrivata una dichiarazione di recesso:
 * da quel momento corrono i 14 giorni per il rimborso.
 */
class AvvisoDiRecessoAlloShop extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public RichiestaDiRecesso $richiesta)
    {
        $this->locale('it');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            replyTo: [$this->richiesta->email],
            subject: 'Recesso dall\'ordine '.$this->richiesta->numero_ordine.' — rimborso entro 14 giorni',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.avviso-di-recesso-allo-shop',
        );
    }
}
