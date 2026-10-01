<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avvisa la società che è arrivato un acquisto, agli indirizzi scelti in
 * Impostazioni Shop & Aste (`shop.order_notification_emails`). Con la ricevuta
 * in PDF allegata e il link all'ordine nel pannello. Va in italiano: la legge
 * la redazione, non il cliente.
 */
class NuovoOrdineAllaSocieta extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->locale('it');
    }

    public function envelope(): Envelope
    {
        $this->order->loadMissing('user');
        $email = $this->order->user->email ?? $this->order->guest_email;
        $nome = $this->order->user->name ?? $this->order->guest_name;

        return new Envelope(
            from: config('mail.from.address'),
            // Rispondendo si scrive al cliente, non a noi stessi.
            replyTo: $email ? [new Address($email, (string) $nome)] : [],
            subject: ($this->order->paid_at ? 'Nuovo ordine ' : 'Nuovo ordine da pagare con bonifico ')
                .$this->order->order_number.' — € '.number_format((float) $this->order->total_price, 2, ',', '.'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.nuovo-ordine-alla-societa');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(ReceiptService::class)->generate($this->order),
                'ricevuta-'.$this->order->order_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
