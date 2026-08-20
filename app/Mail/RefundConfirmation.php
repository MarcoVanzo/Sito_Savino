<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?float $refundAmount = null,
    ) {
        $this->refundAmount = $refundAmount ?? (float) $order->total_price;
        $this->order->loadMissing('user');

        // La mail parte da un worker: la lingua va presa dall'ordine,
        // non dalla richiesta, che a quel punto non esiste più.
        $this->locale($order->locale ?? 'it');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.refund.subject', ['number' => $this->order->order_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-confirmation',
        );
    }
}
