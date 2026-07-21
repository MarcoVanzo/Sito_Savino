<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuctionPaymentDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_orders_command_does_not_cancel_an_auction_order_after_one_hour(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();

        $auction = Auction::factory()->ended()->create(['current_bid' => 100]);
        $auction->forceFill([
            'winner_user_id' => $winner->id,
            'winner_checkout_token' => $token,
            // Deadline dell'asta ancora aperta: 48h di default
            'winner_checkout_deadline' => now()->addHours(47),
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $winner->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
            'created_at' => now()->subHours(3),
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(
            OrderStatus::Pending,
            $order->fresh()->status,
            "L'ordine d'asta ha 48h per essere pagato, non deve essere annullato dopo un'ora."
        );
    }

    public function test_unpaid_orders_command_still_cancels_a_normal_abandoned_checkout(): void
    {
        $order = Order::factory()->create([
            'order_token' => Str::uuid()->toString(),
            'payment_gateway' => PaymentGateway::Stripe,
            'created_at' => now()->subHours(3),
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_pending_order_does_not_block_reassignment_to_the_next_bidder(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $token = Str::uuid()->toString();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200]);

        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 150]);

        // Il primo vincitore apre il checkout ma non completa il pagamento,
        // e la deadline dell'asta è ormai passata.
        $auction->forceFill([
            'winner_user_id' => $first->id,
            'winner_checkout_token' => $token,
            'winner_checkout_deadline' => now()->subMinute(),
            'current_winner_attempt' => 1,
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $first->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
            'paid_at' => null,
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        app(AuctionService::class)->checkWinnerPayments();

        $auction->refresh();

        $this->assertSame($second->id, $auction->winner_user_id, "L'asta deve passare al secondo offerente.");
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status, "L'ordine non pagato va annullato.");
    }

    public function test_paid_order_keeps_the_auction_assigned_to_its_winner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $token = Str::uuid()->toString();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200]);

        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 150]);

        $auction->forceFill([
            'winner_user_id' => $first->id,
            'winner_checkout_token' => $token,
            'winner_checkout_deadline' => now()->subMinute(),
            'current_winner_attempt' => 1,
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $first->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
        ]);
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()->subMinutes(5)])->save();

        app(AuctionService::class)->checkWinnerPayments();

        $this->assertSame($first->id, $auction->fresh()->winner_user_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
