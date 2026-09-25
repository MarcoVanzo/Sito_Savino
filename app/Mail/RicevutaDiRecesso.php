<?php

namespace App\Mail;

use App\Models\RichiestaDiRecesso;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * La ricevuta del recesso, su supporto durevole (art. 54-bis c. 5 del Codice
 * del Consumo): contenuto della dichiarazione, data e ora dell'invio.
 * Non implementa ShouldQueue di proposito: deve partire subito.
 */
class RicevutaDiRecesso extends Mailable
{
    public function __construct(public RichiestaDiRecesso $richiesta)
    {
        $this->locale($richiesta->lingua ?: 'it');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.recesso.subject', ['number' => $this->richiesta->numero_ordine]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ricevuta-di-recesso');
    }
}
