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

    protected function setUp(): void
    {
        parent::setUp();

        // Tutto quello che si verifica qui sotto è un confronto fra istanti
        // (deadline di pagamento, età dell'ordine). Con l'orologio reale la
        // distanza fra il dato scritto e il momento in cui il comando lo legge
        // dipende da quanto ha impiegato la suite ad arrivare fin qui: un
        // margine di un minuto è a un soffio dal fallimento intermittente in CI.
        // Congelando il tempo il margine diventa esatto e si può far scorrere
        // l'orologio con travel() solo dove serve davvero.
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_unpaid_orders_command_does_not_cancel_an_auction_order_after_one_hour(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();

        $auction = Auction::factory()->ended()->create(['current_bid' => 100]);
        $auction->forceFill([
            'winner_user_id' => $winner->id,
            'winner_checkout_token' => $token,
            // Finestra di pagamento dell'asta: 48h di default.
            'winner_checkout_deadline' => now()->addHours(48),
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $winner->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        // Tre ore dopo: la soglia dell'ora per i checkout abbandonati è passata
        // da un pezzo, ma la finestra dell'asta è ancora aperta.
        $this->travel(3)->hours();

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(
            OrderStatus::Pending,
            $order->fresh()->status,
            "L'ordine d'asta ha 48h per essere pagato, non deve essere annullato dopo un'ora."
        );
    }

    public function test_unpaid_orders_command_leaves_a_digital_checkout_alone_within_the_first_hour(): void
    {
        $order = Order::factory()->create([
            'order_token' => Str::uuid()->toString(),
            'payment_gateway' => PaymentGateway::Stripe,
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        // 59 minuti: siamo dentro la finestra, il cliente può ancora pagare.
        $this->travel(59)->minutes();

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(
            OrderStatus::Pending,
            $order->fresh()->status,
            'Prima dei 60 minuti il checkout non è abbandonato: annullarlo toglie lo stock a un ordine ancora pagabile.'
        );
    }

    public function test_unpaid_orders_command_still_cancels_a_normal_abandoned_checkout(): void
    {
        $order = Order::factory()->create([
            'order_token' => Str::uuid()->toString(),
            'payment_gateway' => PaymentGateway::Stripe,
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        // Un minuto oltre la soglia dell'ora.
        $this->travel(61)->minutes();

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_auction_is_not_reassigned_before_the_deadline_expires(): void
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
            'winner_checkout_deadline' => now()->addHours(48),
            'current_winner_attempt' => 1,
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $first->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
            'paid_at' => null,
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        // Un minuto prima della scadenza: il vincitore è ancora in tempo.
        $this->travel(47)->hours();
        $this->travel(59)->minutes();

        $this->assertSame(0, app(AuctionService::class)->checkWinnerPayments());

        $auction->refresh();

        $this->assertSame($first->id, $auction->winner_user_id, "Finché la deadline non è scaduta l'asta resta al primo vincitore.");
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status, "L'ordine ancora pagabile non va annullato.");
    }

    public function test_pending_order_does_not_block_reassignment_to_the_next_bidder(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $token = Str::uuid()->toString();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200]);

        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 150]);

        // Il primo vincitore apre il checkout ma non completa il pagamento.
        $auction->forceFill([
            'winner_user_id' => $first->id,
            'winner_checkout_token' => $token,
            'winner_checkout_deadline' => now()->addHours(48),
            'current_winner_attempt' => 1,
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $first->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
            'paid_at' => null,
        ]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        // La finestra di pagamento è scaduta.
        $this->travel(49)->hours();

        app(AuctionService::class)->checkWinnerPayments();

        $auction->refresh();

        $this->assertSame($second->id, $auction->winner_user_id, "L'asta deve passare al secondo offerente.");
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status, "L'ordine non pagato va annullato.");
        $this->assertTrue(
            $auction->winner_checkout_deadline->greaterThan(now()),
            'Il nuovo vincitore riparte con una finestra di pagamento aperta.'
        );
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
            'winner_checkout_deadline' => now()->addHours(48),
            'current_winner_attempt' => 1,
        ])->save();

        $order = Order::factory()->create([
            'user_id' => $first->id,
            'order_token' => $token,
            'payment_gateway' => PaymentGateway::Stripe,
        ]);
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

        // Anche a deadline scaduta, un ordine già pagato non si tocca.
        $this->travel(49)->hours();

        app(AuctionService::class)->checkWinnerPayments();

        $this->assertSame($first->id, $auction->fresh()->winner_user_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
