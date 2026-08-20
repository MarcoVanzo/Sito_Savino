<?php

namespace App\Mail;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuctionWon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Auction $auction,
        public User $winner,
    ) {
        // La mail parte da un worker: la lingua è quella scelta dal vincitore
        // alla registrazione, non quella della richiesta corrente.
        $this->locale($winner->locale ?? 'it');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('emails.auction_won.subject', ['title' => $this->auction->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auction-won',
        );
    }
}
