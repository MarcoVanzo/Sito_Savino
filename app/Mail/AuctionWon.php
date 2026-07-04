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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: "Hai vinto l'asta: {$this->auction->title}!",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auction-won',
        );
    }
}
