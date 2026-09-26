<?php

namespace Tests\Feature\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\StockMovementType;
use App\Mail\RefundConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * Un rimborso parziale non e' un rimborso totale.
 *
 * Il webhook trattava ogni rimborso come completo: restituite le sole spese
 * di spedizione, l'ordine passava a Rimborsato, la merce (ancora dal
 * cliente) tornava in giacenza e partiva l'email di rimborso completo. Ora
 * solo il rimborso totale cambia stato; quello parziale registra l'importo.
 */
class RimborsiParzialiTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    private const SEGRETO_STRIPE = 'whsec_test_rimborsi';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->configureFakePayPal();
        config()->set('services.stripe.secret', 'sk_test_finto');
        config()->set('services.stripe.webhook_secret', self::SEGRETO_STRIPE);
    }

    /**
     * Ordine pagato da 50 €, con tre pezzi scaricati al checkout (stock 10 → 7).
     *
     * @return array{Order, Product}
     */
    private function ordinePagato(PaymentGateway $gateway, string $paymentId): array
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 3, 'price_at_time_of_purchase' => 10,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'order_id' => $order->id,
            'quantity' => -3, 'type' => StockMovementType::Sale, 'notes' => 'checkout',
        ]);

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'payment_gateway' => $gateway,
            'payment_id' => $paymentId,
            'paid_at' => now(),
            'total_price' => 50.00,
        ])->save();

        return [$order->refresh(), $product];
    }

    /**
     * Evento Stripe firmato come lo firma Stripe (t=…,v1=HMAC).
     *
     * @param  array<string, mixed>  $charge
     */
    private function webhookStripe(array $charge): TestResponse
    {
        $corpo = json_encode([
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => ['object' => ['object' => 'charge', ...$charge]],
        ]);
        $t = time();
        $firma = hash_hmac('sha256', "{$t}.{$corpo}", self::SEGRETO_STRIPE);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$firma}",
        ], $corpo);
    }

    /**
     * @param  array<string, mixed>|null  $cattura  la cattura come la rilegge PayPal; null = non rileggibile
     * @param  float|null  $cumulato  total_refunded_amount riportato dall'evento
     */
    private function webhookPayPal(string $captureId, ?array $cattura, ?float $cumulato = null): TestResponse
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            "*/v2/payments/captures/{$captureId}" => $cattura !== null
                ? Http::response(['id' => $captureId, ...$cattura])
                : Http::response(['name' => 'INTERNAL_SERVER_ERROR'], 500),
        ]);

        $rimborso = [
            'id' => 'REFUND-'.uniqid(),
            'status' => 'COMPLETED',
            'amount' => ['value' => '10.00', 'currency_code' => 'EUR'],
            'links' => [['rel' => 'up', 'href' => "https://api-m.sandbox.paypal.com/v2/payments/captures/{$captureId}"]],
        ];

        if ($cumulato !== null) {
            $rimborso['seller_payable_breakdown'] = [
                'total_refunded_amount' => ['value' => number_format($cumulato, 2, '.', ''), 'currency_code' => 'EUR'],
            ];
        }

        return $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => $rimborso,
        ]);
    }

    #[Test]
    public function stripe_rimborso_parziale_registra_l_importo_e_lascia_l_ordine_com_e(): void
    {
        [$order, $product] = $this->ordinePagato(PaymentGateway::Stripe, 'pi_parziale');

        $this->webhookStripe([
            'payment_intent' => 'pi_parziale',
            'amount' => 5000,
            'amount_refunded' => 1000,
            'refunded' => false,
        ])->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('10.00', (string) $order->refunded_amount);
        $this->assertSame(7, (int) $product->fresh()->stock, 'La merce e\' ancora dal cliente.');
        Mail::assertNotQueued(RefundConfirmation::class);
    }

    #[Test]
    public function stripe_rimborso_totale_rimborsa_l_ordine_e_rimette_la_merce(): void
    {
        [$order, $product] = $this->ordinePagato(PaymentGateway::Stripe, 'pi_totale');

        $this->webhookStripe([
            'payment_intent' => 'pi_totale',
            'amount' => 5000,
            'amount_refunded' => 5000,
            'refunded' => true,
        ])->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame('50.00', (string) $order->refunded_amount);
        $this->assertSame(10, (int) $product->fresh()->stock);
        Mail::assertQueued(RefundConfirmation::class);
    }

    #[Test]
    public function stripe_due_parziali_che_arrivano_al_totale_chiudono_l_ordine(): void
    {
        [$order] = $this->ordinePagato(PaymentGateway::Stripe, 'pi_due');

        $this->webhookStripe(['payment_intent' => 'pi_due', 'amount' => 5000, 'amount_refunded' => 2000, 'refunded' => false])->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $this->webhookStripe(['payment_intent' => 'pi_due', 'amount' => 5000, 'amount_refunded' => 5000, 'refunded' => true])->assertOk();
        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
    }

    #[Test]
    public function paypal_rimborso_parziale_registra_l_importo_e_lascia_l_ordine_com_e(): void
    {
        [$order, $product] = $this->ordinePagato(PaymentGateway::PayPal, 'CAPTURE-PARZ');

        $this->webhookPayPal('CAPTURE-PARZ', [
            'status' => 'PARTIALLY_REFUNDED',
            'amount' => ['value' => '50.00', 'currency_code' => 'EUR'],
        ], 10.00)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('10.00', (string) $order->refunded_amount);
        $this->assertSame(7, (int) $product->fresh()->stock);
        Mail::assertNotQueued(RefundConfirmation::class);
    }

    #[Test]
    public function paypal_rimborso_totale_rimborsa_l_ordine(): void
    {
        [$order, $product] = $this->ordinePagato(PaymentGateway::PayPal, 'CAPTURE-TOT');

        $this->webhookPayPal('CAPTURE-TOT', [
            'status' => 'REFUNDED',
            'amount' => ['value' => '50.00', 'currency_code' => 'EUR'],
        ], 50.00)->assertOk();

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
        Mail::assertQueued(RefundConfirmation::class);
    }

    #[Test]
    public function paypal_senza_cattura_leggibile_decide_il_cumulato_dell_evento(): void
    {
        [$order] = $this->ordinePagato(PaymentGateway::PayPal, 'CAPTURE-CUM');

        $this->webhookPayPal('CAPTURE-CUM', null, 10.00)->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $this->webhookPayPal('CAPTURE-CUM', null, 50.00)->assertOk();
        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
    }

    #[Test]
    public function paypal_senza_nessuna_informazione_sull_importo_va_in_revisione_e_non_rimborsa(): void
    {
        [$order, $product] = $this->ordinePagato(PaymentGateway::PayPal, 'CAPTURE-BUIO');

        $this->webhookPayPal('CAPTURE-BUIO', null)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(7, (int) $product->fresh()->stock);
        $this->assertStringContainsString('REVISIONE MANUALE', (string) $order->notes);
    }
}
