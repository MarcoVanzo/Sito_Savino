<?php

namespace Tests\Feature\Observability;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * I blocchi `catch` del trait dei webhook registravano `Log::error` e
 * proseguivano. Con `LOG_CHANNEL=stderr` quel messaggio non sopravvive al
 * riavvio del container: un pagamento incassato dal gateway ma non registrato
 * qui — denaro preso senza ordine confermato — non lasciava nessuna traccia
 * che qualcuno potesse ritrovare.
 *
 * Questi test verificano che l'eccezione arrivi al gestore degli errori (e
 * quindi a Sentry) e, insieme, che il webhook continui a NON propagare
 * l'errore al gateway.
 */
class WebhookFailureReportingTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->configureFakePayPal();
    }

    /**
     * Si fa fallire la notifica al pannello, che è l'ultimo passo dentro il
     * `try`: il pagamento a quel punto è già registrato, quindi si esercita
     * esattamente il caso peggiore — incasso avvenuto ed elaborazione
     * interrotta a metà.
     */
    private function breakNotifications(): void
    {
        $broken = $this->createMock(AdminNotificationService::class);
        $broken->method('notifyPaymentReceived')
            ->willThrowException(new RuntimeException('pannello irraggiungibile'));

        $this->app->instance(AdminNotificationService::class, $broken);
    }

    #[Test]
    public function un_errore_nel_webhook_viene_segnalato(): void
    {
        Exceptions::fake();

        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->breakNotifications();
        $this->fakePayPal($order->id, 'CAPTURE-1');

        $this->postWebhook()->assertStatus(500);

        Exceptions::assertReported(
            fn (RuntimeException $e) => $e->getMessage() === 'pannello irraggiungibile',
        );
    }

    #[Test]
    public function il_pagamento_resta_registrato_anche_se_il_resto_fallisce(): void
    {
        // La transazione si è già chiusa quando il passo successivo fallisce:
        // il denaro è incassato e l'ordine deve restare pagato, altrimenti il
        // cliente avrebbe pagato senza avere un ordine.
        Exceptions::fake();

        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->breakNotifications();
        $this->fakePayPal($order->id, 'CAPTURE-1');

        $this->postWebhook();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-1', $order->payment_id);
    }
}
