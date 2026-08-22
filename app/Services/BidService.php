<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Events\BidPlaced;
use App\Mail\AuctionOutbid;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BidService
{
    /**
     * Piazza un'offerta su un'asta.
     *
     * @throws \InvalidArgumentException
     */
    public function placeBid(Auction $auction, User $user, float $amount): Bid
    {
        return DB::transaction(function () use ($auction, $user, $amount) {
            // Lock sull'asta per evitare race condition
            $auction = Auction::lockForUpdate()->find($auction->id);

            // 1. Validazione stato asta
            if ($auction->status !== AuctionStatus::Active) {
                throw new \InvalidArgumentException('L\'asta non è attiva.');
            }

            // 2. Validazione data fine
            if ($auction->end_date->isPast()) {
                throw new \InvalidArgumentException('L\'asta è già terminata.');
            }

            // 3. Validazione: non puoi superare te stesso
            $highestBid = $auction->highestBid();
            if ($highestBid && $highestBid->user_id === $user->id) {
                throw new \InvalidArgumentException('Sei già il miglior offerente. Non puoi superare la tua stessa offerta.');
            }

            // 4. Calcolo offerta minima
            $bidIncrement = (float) ($auction->bid_increment ?: SiteSetting::get('auctions.min_bid_increment', 5));

            if ($highestBid) {
                $minimumBid = round((float) $auction->current_bid + $bidIncrement, 2);
            } else {
                $minimumBid = round((float) $auction->starting_price + $bidIncrement, 2);
            }

            // 5. Validazione importo minimo
            if ($amount < $minimumBid) {
                throw new \InvalidArgumentException(
                    "L'offerta minima è di €".number_format($minimumBid, 2, ',', '.').'.'
                );
            }

            // 6. Validazione salto massimo
            $maxBidJump = (float) ($auction->max_bid_jump ?: SiteSetting::get('auctions.max_bid_jump', 100));
            $currentBase = $highestBid ? (float) $auction->current_bid : (float) $auction->starting_price;
            $maximumBid = round($currentBase + $maxBidJump, 2);

            if ($amount > $maximumBid) {
                throw new \InvalidArgumentException(
                    "L'offerta massima consentita è di €".number_format($maximumBid, 2, ',', '.').'.'
                );
            }

            // 7. Creazione offerta
            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'placed_at' => now(),
            ]);

            // is_valid is not mass-assignable (security), set it explicitly
            $bid->forceFill(['is_valid' => true])->save();

            // 8. Aggiornamento offerta corrente sull'asta
            $auction->update(['current_bid' => $amount]);

            // 9. Anti-sniping
            $antiSnipeMinutes = (int) SiteSetting::get('auctions.anti_snipe_minutes', 5);
            if ($auction->isInAntiSnipePeriod($antiSnipeMinutes)) {
                $auction->extendByMinutes($antiSnipeMinutes);
            }

            // Ricarica l'asta per avere l'end_date aggiornata
            $auction->refresh();

            // 10. Broadcast evento BidPlaced
            $bidsCount = $auction->bids()->valid()->count();

            broadcast(new BidPlaced(
                auctionId: $auction->id,
                bidAmount: $amount,
                bidderName: AuctionService::maskUsername($user->name),
                bidsCount: $bidsCount,
                endsAt: $auction->end_date->toIso8601String(),
            ));

            // 11. Email al precedente miglior offerente
            if ($highestBid && $highestBid->user_id !== $user->id) {
                $previousBidder = User::find($highestBid->user_id);

                if ($previousBidder) {
                    Mail::to($previousBidder->email)->queue(
                        new AuctionOutbid($auction, $bid, $previousBidder)
                    );
                }
            }

            return $bid;
        });
    }
}
