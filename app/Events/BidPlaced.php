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
        public int $auction_id,
        public float $bid_amount,
        public string $bidder_name,
        public int $bids_count,
        public string $ends_at,
    ) {}

    /**
     * Canale di broadcast per l'asta.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('auction.'.$this->auction_id);
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
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auction_id,
            'bid_amount' => $this->bid_amount,
            'bidder_name' => $this->bidder_name,
            'bids_count' => $this->bids_count,
            'ends_at' => $this->ends_at,
        ];
    }
}
