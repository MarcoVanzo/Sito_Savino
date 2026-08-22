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
            $highestBid = $auction->highestBid();

            $this->verificaCheSiPossaOffrire($auction, $user, $highestBid);
            $this->verificaImporto($auction, $amount, $highestBid !== null);

            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'placed_at' => now(),
            ]);

            // is_valid is not mass-assignable (security), set it explicitly
            $bid->forceFill(['is_valid' => true])->save();

            $auction->update(['current_bid' => $amount]);

            $this->prolungaSeInDirittura($auction);

            // Ricarica l'asta per avere l'end_date aggiornata
            $auction->refresh();

            broadcast(new BidPlaced(
                auctionId: $auction->id,
                bidAmount: $amount,
                bidderName: AuctionService::maskUsername($user->name),
                bidsCount: $auction->bids()->valid()->count(),
                endsAt: $auction->end_date->toIso8601String(),
            ));

            $this->avvisaChiEStatoSuperato($auction, $bid, $highestBid, $user);

            return $bid;
        });
    }

    /**
     * L'asta e' aperta e l'offerente non e' gia' il miglior offerente.
     *
     * Superare se stessi alzerebbe il prezzo senza cambiare l'esito: e' un
     * errore di chi offre, non una gara con qualcun altro.
     */
    private function verificaCheSiPossaOffrire(Auction $auction, User $user, ?Bid $highestBid): void
    {
        if ($auction->status !== AuctionStatus::Active) {
            throw new \InvalidArgumentException('L\'asta non è attiva.');
        }

        if ($auction->end_date->isPast()) {
            throw new \InvalidArgumentException('L\'asta è già terminata.');
        }

        if ($highestBid && $highestBid->user_id === $user->id) {
            throw new \InvalidArgumentException('Sei già il miglior offerente. Non puoi superare la tua stessa offerta.');
        }
    }

    /**
     * L'importo sta fra il rilancio minimo e il salto massimo.
     *
     * Il tetto esiste perche' un'offerta enormemente sopra la precedente
     * chiude di fatto l'asta, per errore di battitura o per dispetto.
     */
    private function verificaImporto(Auction $auction, float $amount, bool $ciSonoOfferte): void
    {
        $base = $ciSonoOfferte ? (float) $auction->current_bid : (float) $auction->starting_price;

        $bidIncrement = (float) ($auction->bid_increment ?: SiteSetting::get('auctions.min_bid_increment', 5));
        $minimumBid = round($base + $bidIncrement, 2);

        if ($amount < $minimumBid) {
            throw new \InvalidArgumentException(
                "L'offerta minima è di €".number_format($minimumBid, 2, ',', '.').'.'
            );
        }

        $maxBidJump = (float) ($auction->max_bid_jump ?: SiteSetting::get('auctions.max_bid_jump', 100));
        $maximumBid = round($base + $maxBidJump, 2);

        if ($amount > $maximumBid) {
            throw new \InvalidArgumentException(
                "L'offerta massima consentita è di €".number_format($maximumBid, 2, ',', '.').'.'
            );
        }
    }

    /**
     * Anti-sniping: un'offerta all'ultimo secondo sposta in avanti la chiusura,
     * cosi' chi era in testa ha il tempo di rispondere.
     */
    private function prolungaSeInDirittura(Auction $auction): void
    {
        $antiSnipeMinutes = (int) SiteSetting::get('auctions.anti_snipe_minutes', 5);

        if ($auction->isInAntiSnipePeriod($antiSnipeMinutes)) {
            $auction->extendByMinutes($antiSnipeMinutes);
        }
    }

    /**
     * Email a chi era in testa fino a un attimo fa.
     */
    private function avvisaChiEStatoSuperato(Auction $auction, Bid $bid, ?Bid $highestBid, User $user): void
    {
        if (! $highestBid || $highestBid->user_id === $user->id) {
            return;
        }

        $previousBidder = User::find($highestBid->user_id);

        if ($previousBidder) {
            Mail::to($previousBidder->email)->queue(
                new AuctionOutbid($auction, $bid, $previousBidder)
            );
        }
    }
}
