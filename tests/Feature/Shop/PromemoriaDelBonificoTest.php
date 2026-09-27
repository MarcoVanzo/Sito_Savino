<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Mail\OrderPaymentReminder;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `order:check-unpaid` gira ogni dieci minuti e la finestra del promemoria
 * dura dal quinto al settimo giorno: senza un segno di "gia' inviato" il
 * cliente che sceglie il bonifico riceveva circa 288 email uguali.
 */
class PromemoriaDelBonificoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function il_promemoria_parte_una_volta_sola_per_ordine(): void
    {
        Mail::fake();

        $order = Order::factory()->create([
            'payment_gateway' => PaymentGateway::BankTransfer,
            'guest_email' => 'cliente@example.test',
        ]);
        $order->forceFill(['status' => OrderStatus::Pending, 'created_at' => now()->subDays(6)])->save();

        $this->artisan('order:check-unpaid')->assertSuccessful();
        $this->travel(10)->minutes();
        $this->artisan('order:check-unpaid')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('order:check-unpaid')->assertSuccessful();

        Mail::assertQueued(OrderPaymentReminder::class, 1);
    }
}
