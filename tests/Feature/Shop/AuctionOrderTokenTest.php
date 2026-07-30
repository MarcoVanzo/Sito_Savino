<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Auction;
use App\Models\Order;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L'ordine nato da un'asta usava come `order_token` lo stesso valore di
 * `winner_checkout_token`. Sono due segreti con circolazione diversa — il
 * primo apre il dettaglio ordine e la ricevuta PDF senza login, il secondo
 * viaggia via mail — e non devono più coincidere.
 */
class AuctionOrderTokenTest extends TestCase
{
    use RefreshDatabase;

    private function endedAuctionWithWinner(User $winner): Auction
    {
        $auction = Auction::factory()->ended()->create(['current_bid' => 100]);

        $auction->forceFill([
            'winner_user_id' => $winner->id,
            'winner_checkout_token' => Str::uuid()->toString(),
            'winner_checkout_deadline' => now()->addHours(48),
            'current_winner_attempt' => 1,
        ])->save();

        return $auction;
    }

    public function test_winner_order_is_found_through_the_auction_and_not_the_token(): void
    {
        $winner = User::factory()->create();
        $auction = $this->endedAuctionWithWinner($winner);

        $order = Order::factory()->create(['user_id' => $winner->id]);
        $order->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Pending])->save();

        $found = app(AuctionService::class)->getWinnerOrder($auction);

        $this->assertNotNull($found);
        $this->assertSame($order->id, $found->id);
        $this->assertNotSame(
            $auction->winner_checkout_token,
            $found->order_token,
            "L'ordine d'asta non deve riusare il token di checkout come order_token."
        );
    }

    public function test_reassignment_ignores_the_order_of_the_previous_winner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $auction = $this->endedAuctionWithWinner($first);

        $oldOrder = Order::factory()->create(['user_id' => $first->id]);
        $oldOrder->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Cancelled])->save();

        // L'asta passa al secondo offerente.
        $auction->forceFill([
            'winner_user_id' => $second->id,
            'winner_checkout_token' => Str::uuid()->toString(),
            'current_winner_attempt' => 2,
        ])->save();

        $this->assertNull(
            app(AuctionService::class)->getWinnerOrder($auction->fresh()),
            "L'ordine annullato del vincitore precedente non deve valere come ordine del nuovo vincitore."
        );
    }

    public function test_auction_checkout_token_does_not_open_the_order_detail(): void
    {
        $winner = User::factory()->create();
        $auction = $this->endedAuctionWithWinner($winner);

        $order = Order::factory()->create(['user_id' => $winner->id]);
        $order->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Pending])->save();
        $order->refresh();

        // Da guest, con in mano il token arrivato via mail al vincitore.
        $this->get(route('shop.order.show', [
            'orderNumber' => $order->order_number,
            'token' => $auction->winner_checkout_token,
        ]))->assertNotFound();
    }

    public function test_auction_checkout_token_does_not_download_the_receipt(): void
    {
        $winner = User::factory()->create();
        $auction = $this->endedAuctionWithWinner($winner);

        $order = Order::factory()->create(['user_id' => $winner->id]);
        $order->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Paid, 'paid_at' => now()])->save();

        $this->get(route('shop.order.receipt', ['orderToken' => $auction->winner_checkout_token]))
            ->assertNotFound();
    }

    public function test_one_order_per_auction_and_user(): void
    {
        $winner = User::factory()->create();
        $auction = $this->endedAuctionWithWinner($winner);

        $first = Order::factory()->create(['user_id' => $winner->id]);
        $first->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Pending])->save();

        $this->expectException(UniqueConstraintViolationException::class);

        $second = Order::factory()->create(['user_id' => $winner->id]);
        $second->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Pending])->save();
    }
}
