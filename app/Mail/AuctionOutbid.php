<?php

namespace App\Mail;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuctionOutbid extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * $recipient è l'offerente superato: la mail parte da un worker, quindi la
     * sua lingua va letta da lui e non dalla richiesta di chi ha rilanciato —
     * che è un altro utente, potenzialmente su un'altra versione del sito.
     */
    public function __construct(
        public Auction $auction,
        public Bid $outbidBid,
        public ?User $recipient = null,
    ) {
        $this->locale($recipient->locale ?? 'it');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.auction_outbid.subject', ['title' => $this->auction->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auction-outbid',
        );
    }
}
