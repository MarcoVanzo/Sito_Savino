<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\CondizioniDiVendita;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {
        $this->order->loadMissing(['items.product', 'items.variant', 'user']);

        // La mail parte da un worker: la lingua va presa dall'ordine,
        // non dalla richiesta, che a quel punto non esiste più.
        $this->locale($order->locale ?? 'it');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.confirmation.subject', ['number' => $this->order->order_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
        );
    }

    /**
     * Le condizioni di vendita e l'informativa sul recesso, per intero.
     *
     * La conferma del contratto deve darle su un supporto durevole (art. 51
     * c. 7 del Codice del consumo): un link alla pagina non basta, perché la
     * pagina cambia e il cliente deve poter conservare il testo che ha
     * accettato. Il corpo dell'email ne riporta l'essenziale.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $lingua = $this->order->locale ?? 'it';

        return [
            Attachment::fromData(
                fn () => Pdf::loadView('pdf.condizioni-di-vendita', [
                    'order' => $this->order,
                    'lingua' => $lingua,
                    'pagine' => CondizioniDiVendita::perLAllegato($lingua),
                ])->output(),
                __('emails.contratto.pdf_filename', [], $lingua),
            )->withMime('application/pdf'),
        ];
    }
}
