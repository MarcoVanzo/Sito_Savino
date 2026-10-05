<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Lang;

/**
 * Conferma al giornalista che la sua richiesta di accredito è stata accolta.
 *
 * Prima il pulsante "Accredita" del pannello cambiava solo lo stato: chi aveva
 * fatto la richiesta non riceveva niente. Non va in coda di proposito: parte
 * dal pannello e chi la manda deve sapere subito se è andata.
 */
class AccreditoStampaConfermato extends Mailable
{
    public function __construct(public ContactMessage $richiesta, public ?string $messaggio = null)
    {
        $lingua = $richiesta->extra_data['lingua'] ?? null;

        $this->locale(in_array($lingua, config('app.supported_locales', ['it']), true) ? $lingua : 'it');
    }

    public function envelope(): Envelope
    {
        // Le risposte vanno all'ufficio stampa, non al mittente di sistema.
        $ufficioStampa = $this->ufficioStampa();

        return new Envelope(
            from: config('mail.from.address'),
            replyTo: $ufficioStampa ? [new Address($ufficioStampa)] : [],
            subject: __('emails.accredito.subject'),
        );
    }

    public function content(): Content
    {
        $dettagli = is_array($this->richiesta->extra_data) ? $this->richiesta->extra_data : [];
        $ruolo = $dettagli['role'] ?? null;

        return new Content(view: 'emails.accredito-stampa-confermato', with: [
            'dettagli' => $dettagli,
            'ruolo' => $ruolo && Lang::has('emails.accredito.roles.'.$ruolo) ? __('emails.accredito.roles.'.$ruolo) : $ruolo,
            'ufficioStampa' => $this->ufficioStampa() ?: 'info@savinodelbenevolley.it',
        ]);
    }

    private function ufficioStampa(): ?string
    {
        return SiteSetting::get('press_email') ?: SiteSetting::get('media_email') ?: null;
    }
}
