<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Enums\OrderStatus;
use App\Mail\AuctionWon;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
            $auction->forceFill(['status' => AuctionStatus::Active])->save();

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

                // La lista e' stata letta prima del lock: nel frattempo un
                // rilancio negli ultimi minuti puo' aver spostato la fine
                // (anti-sniping) o un altro giro averla gia' chiusa. Chiudere
                // sulla copia vecchia assegnava l'asta mentre si offriva ancora.
                if ($auction === null
                    || $auction->status !== AuctionStatus::Active
                    || $auction->end_date->isFuture()) {
                    return;
                }

                $winnerBid = $auction->validBids()->first();

                if ($winnerBid && $auction->isReserveMet()) {
                    $paymentDeadlineHours = (int) SiteSetting::get('auctions.payment_deadline_hours', 48);

                    $auction->forceFill([
                        'status' => AuctionStatus::Ended,
                        'winner_user_id' => $winnerBid->user_id,
                        'winner_checkout_token' => Str::uuid()->toString(),
                        'winner_checkout_deadline' => now()->addHours($paymentDeadlineHours),
                        'current_winner_attempt' => 1,
                    ])->save();

                    $winner = User::find($winnerBid->user_id);

                    if ($winner) {
                        $this->sendAuctionWonMail($auction->fresh(), $winner);
                    }

                    Log::info("Asta #{$auction->id} chiusa. Vincitore: User #{$winnerBid->user_id} con offerta di €{$winnerBid->amount}.");
                } elseif ($winnerBid && ! $auction->isReserveMet()) {
                    $auction->forceFill([
                        'status' => AuctionStatus::Ended,
                    ])->save();

                    Log::info("Asta #{$auction->id} chiusa senza vincitore: prezzo di riserva non raggiunto (riserva: €{$auction->reserve_price}, offerta massima: €{$winnerBid->amount}).");
                } else {
                    $auction->forceFill([
                        'status' => AuctionStatus::Ended,
                    ])->save();

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
            DB::transaction(function () use ($auction, &$processed) {
                $auction = Auction::lockForUpdate()->find($auction->id);

                if (! $this->chiudiIlTurnoDelVincitore($auction)) {
                    return;
                }

                $this->passaAlProssimoOfferente($auction);

                $processed++;
            });
        }

        return $processed;
    }

    /**
     * Il turno del vincitore corrente e' finito senza pagamento?
     *
     * Va controllato il PAGAMENTO, non la semplice esistenza dell'ordine: il
     * vincitore che apre il checkout senza completarlo lascia un ordine
     * Pending, che faceva considerare l'asta come pagata e ne impediva per
     * sempre la riassegnazione all'offerente successivo.
     *
     * L'ordine rimasto aperto oltre il termine non e' piu' pagabile:
     * annullarlo restituisce lo stock tramite OrderObserver. Vanno annullati
     * anche gli stati diversi da Pending (per esempio Processing): ignorarli
     * riassegnava comunque l'asta lasciando lo stock riservato per sempre.
     *
     * @return bool false se non si deve riassegnare niente
     */
    private function chiudiIlTurnoDelVincitore(Auction $auction): bool
    {
        $existingOrder = $this->getWinnerOrder($auction);

        if ($existingOrder?->paid_at !== null) {
            return false; // Pagamento già effettuato
        }

        if (! $existingOrder) {
            return true;
        }

        if (in_array($existingOrder->status, [OrderStatus::Pending, OrderStatus::Processing], true)) {
            // Si rilegge sotto lock (siamo nella transazione di
            // checkWinnerPayments): il pagamento puo' essere arrivato dopo la
            // lettura. Annullare la copia vecchia cancellava un ordine appena
            // pagato e passava il lotto al secondo offerente.
            $attuale = Order::lockForUpdate()->find($existingOrder->id);

            if ($attuale === null || $attuale->payment_id !== null || $attuale->paid_at !== null) {
                return false;
            }

            if (! in_array($attuale->status, [OrderStatus::Pending, OrderStatus::Processing], true)) {
                // Cambiato nel frattempo: lo rivede il prossimo giro.
                return false;
            }

            $attuale->forceFill(['status' => OrderStatus::Cancelled])->save();

            return true;
        }

        if (in_array($existingOrder->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            return true;
        }

        // Spedito/consegnato senza paid_at: situazione anomala che non va
        // risolta automaticamente (la merce è già partita). Nessuna
        // riassegnazione: richiede intervento manuale.
        Log::error("Asta #{$auction->id}: ordine #{$existingOrder->id} in stato {$existingOrder->status->value} senza pagamento registrato — riassegnazione sospesa, verificare manualmente.");

        return false;
    }

    /**
     * Assegna l'asta a chi viene dopo in classifica, o la lascia senza
     * vincitore se non c'e' piu' nessuno.
     */
    private function passaAlProssimoOfferente(Auction $auction): void
    {
        $currentAttempt = (int) $auction->current_winner_attempt;
        $nextIndex = $this->posizioneDelProssimo($auction);
        $nextBid = $this->classificaOfferte($auction)->get($nextIndex);

        // La riserva vale anche per chi subentra: alla chiusura un'offerta
        // sotto riserva non vince (closeEndedAuctions), e riassegnando si
        // vendeva il lotto sotto il prezzo minimo deciso dalla societa'. La
        // classifica e' decrescente: se questa e' sotto, lo sono tutte.
        if ($nextBid && ! $this->raggiungeLaRiserva($auction, (float) $nextBid->amount)) {
            Log::info("Asta #{$auction->id}: l'offerta successiva (€{$nextBid->amount}) e' sotto la riserva (€{$auction->reserve_price}).");

            $nextBid = null;
        }

        if (! $nextBid) {
            // Nessun altro offerente disponibile
            $auction->forceFill([
                'winner_user_id' => null,
                'winner_checkout_token' => null,
                'winner_checkout_deadline' => null,
            ])->save();

            Log::warning("Asta #{$auction->id}: nessun altro offerente disponibile dopo {$currentAttempt} tentativi. Asta senza vincitore.");

            return;
        }

        $paymentDeadlineHours = (int) SiteSetting::get('auctions.payment_deadline_hours', 48);

        $auction->forceFill([
            'winner_user_id' => $nextBid->user_id,
            'winner_checkout_token' => Str::uuid()->toString(),
            'winner_checkout_deadline' => now()->addHours($paymentDeadlineHours),
            // Posizione 1-based del nuovo vincitore in classifica
            'current_winner_attempt' => $nextIndex + 1,
        ])->save();

        $newWinner = User::find($nextBid->user_id);

        if ($newWinner) {
            $this->sendAuctionWonMail($auction->fresh(), $newWinner);
        }

        Log::info("Asta #{$auction->id}: vincitore precedente non ha pagato. Nuovo vincitore: User #{$nextBid->user_id} (tentativo #{$auction->current_winner_attempt}).");
    }

    /**
     * Stessa regola di Auction::isReserveMet, ma sull'offerta di chi subentra
     * e non su `current_bid`, che resta quella del primo vincitore.
     */
    private function raggiungeLaRiserva(Auction $auction, float $offerta): bool
    {
        if (! $auction->reserve_price || (float) $auction->reserve_price <= 0) {
            return true;
        }

        return $offerta >= (float) $auction->reserve_price;
    }

    /**
     * Offerte valide ordinate per importo decrescente, una sola per utente (la
     * piu' alta): lo stesso utente puo' aver rilanciato piu' volte e con
     * rilanci alternati fra due utenti l'asta rimbalzava fra gli stessi due
     * nomi, riassegnandola a chi aveva gia' mancato il pagamento.
     *
     * @return Collection<int, Bid>
     */
    private function classificaOfferte(Auction $auction): Collection
    {
        // Un'offerta senza utente (account cancellato: bids.user_id va a NULL)
        // non ha nessuno a cui assegnare l'asta: la riassegnazione finirebbe su
        // un vincitore nullo, che nessun giro successivo rivede piu'.
        return $auction->validBids()->get()
            ->filter(fn (Bid $bid) => $bid->user_id !== null)
            ->unique('user_id')
            ->values();
    }

    /**
     * Gli utenti che hanno gia' perso il turno sono tutti quelli in classifica
     * fino al vincitore corrente incluso: il prossimo e' quello immediatamente
     * sotto. Se l'offerta del vincitore corrente non e' piu' valida
     * (invalidata a mano) si ricade sul contatore dei tentativi.
     */
    private function posizioneDelProssimo(Auction $auction): int
    {
        $currentIndex = $this->classificaOfferte($auction)->search(
            fn (Bid $bid) => $bid->user_id === $auction->winner_user_id
        );

        return $currentIndex === false
            ? max(0, (int) $auction->current_winner_attempt)
            : $currentIndex + 1;
    }

    /**
     * Invia la mail di vittoria passando l'importo realmente dovuto.
     *
     * `current_bid` non viene aggiornato alla riassegnazione: usarlo nel template
     * faceva arrivare al secondo vincitore l'importo del primo. L'importo viene
     * passato come view data (Mailable::with) per non dover modificare la
     * firma della Mailable.
     */
    protected function sendAuctionWonMail(Auction $auction, User $winner): void
    {
        $mailable = (new AuctionWon($auction, $winner))
            ->with(['winningAmount' => $this->winningAmountFor($auction)]);

        Mail::to($winner->email)->queue($mailable);
    }

    /**
     * Trova l'ordine del vincitore corrente dell'asta.
     *
     * Il legame passa dalla foreign key, non più dall'uguaglianza fra
     * `order_token` e `winner_checkout_token`: quei due token hanno scopi
     * diversi e non devono coincidere. Il filtro sull'utente serve perché
     * dopo una riassegnazione l'asta conserva l'ordine annullato del
     * vincitore precedente.
     */
    public function getWinnerOrder(Auction $auction): ?Order
    {
        if (! $auction->winner_user_id) {
            return null;
        }

        return Order::where('auction_id', $auction->id)
            ->where('user_id', $auction->winner_user_id)
            ->first();
    }

    /**
     * Importo che il vincitore corrente deve pagare.
     *
     * Non coincide necessariamente con `current_bid`: se il primo vincitore non
     * paga entro la deadline, l'asta viene riassegnata all'offerente successivo
     * (checkWinnerPayments), che deve pagare la PROPRIA offerta, non quella più
     * alta in assoluto.
     */
    public function winningAmountFor(Auction $auction): float
    {
        if ($auction->winner_user_id) {
            $winnerBid = Bid::where('auction_id', $auction->id)
                ->where('user_id', $auction->winner_user_id)
                ->valid()
                ->highestFirst()
                ->first();

            if ($winnerBid) {
                return (float) $winnerBid->amount;
            }
        }

        return (float) ($auction->current_bid ?? 0);
    }

    /**
     * Maschera un nome utente mostrando solo i primi 2 caratteri.
     * Es: 'Mario Rossi' → 'Ma***'
     */
    public static function maskUsername(string $name): string
    {
        if (mb_strlen($name) <= 2) {
            return $name.'***';
        }

        return mb_substr($name, 0, 2).'***';
    }
}
