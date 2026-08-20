<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $oldStatus = null,
        public string $newStatus = '',
    ) {
        $this->order->loadMissing('user');

        // La mail parte da un worker: la lingua va presa dall'ordine,
        // non dalla richiesta, che a quel punto non esiste più.
        $this->locale($order->locale ?? 'it');
    }

    public function envelope(): Envelope
    {
        $statusLabel = $this->order->status->getLabel();

        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.status_changed.subject', [
                'number' => $this->order->order_number,
                'status' => $statusLabel,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-changed',
        );
    }
}
