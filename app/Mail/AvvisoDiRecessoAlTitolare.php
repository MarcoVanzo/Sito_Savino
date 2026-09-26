<?php

namespace App\Mail;

use App\Models\RichiestaDiRecesso;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avvisa il titolare di un ordine che qualcuno, da un altro indirizzo email,
 * ha dichiarato il recesso da quell'ordine.
 *
 * Non e' la ricevuta: chi scrive nel modulo un numero d'ordine altrui non
 * deve poter far arrivare a quella persona il proprio nome, il proprio
 * indirizzo o un testo a sua scelta. Porta solo numero d'ordine e data, e
 * dice dove scrivere se non e' stato lui. Parte subito, come la ricevuta:
 * il titolare deve saperlo prima del rimborso.
 */
class AvvisoDiRecessoAlTitolare extends Mailable
{
    public function __construct(public RichiestaDiRecesso $richiesta)
    {
        $this->locale($richiesta->order?->locale ?: ($richiesta->lingua ?: 'it'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.recesso_titolare.subject', ['number' => $this->richiesta->numero_ordine]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.avviso-di-recesso-al-titolare');
    }
}
