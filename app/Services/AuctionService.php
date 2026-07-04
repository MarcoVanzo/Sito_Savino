<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Mail\AuctionWon;
use App\Models\Auction;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuctionService
{
    /**
     * Attiva tutte le aste programmate la cui data di inizio è passata.
     */
    public function activateScheduledAuctions(): int
    {
        $auctions = Auction::readyToActivate()->get();

        foreach ($auctions as $auction) {
            $auction->update(['status' => AuctionStatus::Active]);

            Log::info("Asta #{$auction->id} '{$auction->title}' attivata.");
        }

        return $auctions->count();
    }

    /**
     * Chiude tutte le aste attive la cui data di fine è passata e assegna i vincitori.
     */
    public function closeEndedAuctions(): int
    {
        $auctions = Auction::readyToClose()->get();

        foreach ($auctions as $auction) {
            DB::transaction(function () use ($auction) {
                // Lock per garantire integrità nella determinazione del vincitore
                $auction = Auction::lockForUpdate()->find($auction->id);

                $winnerBid = $auction->validBids()->first();

                if ($winnerBid) {
                    $paymentDeadlineHours = (int) SiteSetting::get('auctions.payment_deadline_hours', 48);

                    $auction->update([
                        'status' => AuctionStatus::Ended,
                        'winner_user_id' => $winnerBid->user_id,
                        'winner_checkout_token' => Str::uuid()->toString(),
                        'winner_checkout_deadline' => now()->addHours($paymentDeadlineHours),
                        'current_winner_attempt' => 1,
                    ]);

                    $winner = User::find($winnerBid->user_id);

                    if ($winner) {
                        Mail::to($winner->email)->queue(new AuctionWon($auction->fresh(), $winner));
                    }

                    Log::info("Asta #{$auction->id} chiusa. Vincitore: User #{$winnerBid->user_id} con offerta di €{$winnerBid->amount}.");
                } else {
                    $auction->update([
                        'status' => AuctionStatus::Ended,
                    ]);

                    Log::info("Asta #{$auction->id} chiusa senza offerte valide.");
                }
            });
        }

        return $auctions->count();
    }

    /**
     * Verifica i pagamenti dei vincitori e assegna al prossimo offerente se scaduti.
     */
    public function checkWinnerPayments(): int
    {
        $auctions = Auction::ended()
            ->whereNotNull('winner_user_id')
            ->whereNotNull('winner_checkout_deadline')
            ->where('winner_checkout_deadline', '<=', now())
            ->get();

        $processed = 0;

        foreach ($auctions as $auction) {
            // Verifica se il vincitore ha già pagato
            $existingOrder = $this->getWinnerOrder($auction);

            if ($existingOrder) {
                continue; // Pagamento già effettuato
            }

            DB::transaction(function () use ($auction, &$processed) {
                $auction = Auction::lockForUpdate()->find($auction->id);

                $currentAttempt = $auction->current_winner_attempt;

                // Prendi tutte le offerte valide ordinate per importo decrescente
                $validBids = $auction->validBids()->get();

                // Il prossimo offerente è quello all'indice current_winner_attempt
                // (indice 0 = primo vincitore, indice 1 = secondo, ecc.)
                $nextBid = $validBids->get($currentAttempt);

                if ($nextBid) {
                    $paymentDeadlineHours = (int) SiteSetting::get('auctions.payment_deadline_hours', 48);

                    $auction->update([
                        'winner_user_id' => $nextBid->user_id,
                        'winner_checkout_token' => Str::uuid()->toString(),
                        'winner_checkout_deadline' => now()->addHours($paymentDeadlineHours),
                        'current_winner_attempt' => $currentAttempt + 1,
                    ]);

                    $newWinner = User::find($nextBid->user_id);

                    if ($newWinner) {
                        Mail::to($newWinner->email)->queue(new AuctionWon($auction->fresh(), $newWinner));
                    }

                    Log::info("Asta #{$auction->id}: vincitore precedente non ha pagato. Nuovo vincitore: User #{$nextBid->user_id} (tentativo #{$auction->current_winner_attempt}).");
                } else {
                    // Nessun altro offerente disponibile
                    $auction->update([
                        'winner_user_id' => null,
                        'winner_checkout_token' => null,
                        'winner_checkout_deadline' => null,
                    ]);

                    Log::warning("Asta #{$auction->id}: nessun altro offerente disponibile dopo {$currentAttempt} tentativi. Asta senza vincitore.");
                }

                $processed++;
            });
        }

        return $processed;
    }

    /**
     * Trova l'ordine del vincitore tramite il checkout token dell'asta.
     */
    public function getWinnerOrder(Auction $auction): ?Order
    {
        if (! $auction->winner_checkout_token) {
            return null;
        }

        return Order::where('order_token', $auction->winner_checkout_token)->first();
    }

    /**
     * Maschera un nome utente mostrando solo i primi 2 caratteri.
     * Es: 'Mario Rossi' → 'Ma***'
     */
    public static function maskUsername(string $name): string
    {
        if (mb_strlen($name) <= 2) {
            return $name . '***';
        }

        return mb_substr($name, 0, 2) . '***';
    }
}
