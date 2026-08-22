<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $auctionId,
        public float $bidAmount,
        public string $bidderName,
        public int $bidsCount,
        public string $endsAt,
    ) {}

    /**
     * Canale di broadcast per l'asta.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('auction.'.$this->auctionId);
    }

    /**
     * Nome dell'evento broadcast.
     */
    public function broadcastAs(): string
    {
        return 'BidPlaced';
    }

    /**
     * Dati da inviare con l'evento broadcast.
     *
     * Le chiavi restano in snake_case: sono il contratto con il front-end,
     * che le legge cosi' anche dalle risposte JSON dell'asta.
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'bid_amount' => $this->bidAmount,
            'bidder_name' => $this->bidderName,
            'bids_count' => $this->bidsCount,
            'ends_at' => $this->endsAt,
        ];
    }
}
