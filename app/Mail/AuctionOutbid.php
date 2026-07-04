<?php

namespace App\Mail;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuctionOutbid extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Auction $auction,
        public Bid $outbidBid,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: "Sei stato superato nell'asta: {$this->auction->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auction-outbid',
        );
    }
}
