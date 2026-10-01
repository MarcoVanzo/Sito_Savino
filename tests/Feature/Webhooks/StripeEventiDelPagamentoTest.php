<?php

namespace Tests\Feature\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Order;
use App\Services\AvvisoTecnico;
use App\Services\Payments\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * Gli eventi di Stripe oltre al pagamento immediato con carta.
 *
 * `checkout.session.completed` valeva come incasso anche quando la sessione
 * si chiudeva `unpaid` (SEPA, bonifico): l'ordine si confermava prima che il
 * denaro arrivasse. E le contestazioni non arrivavano a nessuno.
 */
class StripeEventiDelPagamentoTest extends TestCase
{
    use RefreshDatabase;

    private const SEGRETO = 'whsec_test_eventi';

    /** @var list<array{params: array<string, mixed>, headers: array<int, string>}> */
    private array $richieste = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('services.stripe.secret', 'sk_test_finto');
        config()->set('services.stripe.webhook_secret', self::SEGRETO);
        config()->set('services.avvisi.email', 'allarmi@example.test');

        $test = $this;
        ApiRequestor::setHttpClient(new class($test) implements ClientInterface
        {
            public function __construct(private StripeEventiDelPagamentoTest $test) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->test->registra($params, $headers);

                return [json_encode(['id' => 're_1', 'object' => 'refund', 'url' => 'https://checkout.stripe.com/c/cs_1']), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $headers
     */
    public function registra(array $params, array $headers): void
    {
        $this->richieste[] = ['params' => $params, 'headers' => $headers];
    }

    private function ordineInAttesa(): Order
    {
        $order = Order::factory()->create();
        $order->forceFill([
            'status' => OrderStatus::Pending,
            'payment_gateway' => PaymentGateway::Stripe,
            'payment_id' => null,
            'paid_at' => null,
            'total_price' => 40.00,
        ])->save();

        return $order->refresh();
    }

    /** @param  array<string, mixed>  $oggetto */
    private function evento(string $tipo, array $oggetto): TestResponse
    {
        $corpo = json_encode([
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $tipo,
            'data' => ['object' => $oggetto],
        ]);
        $t = time();
        $firma = hash_hmac('sha256', "{$t}.{$corpo}", self::SEGRETO);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$firma}",
        ], $corpo);
    }

    /** @return array<string, mixed> */
    private function sessione(Order $order, string $statoPagamento): array
    {
        return [
            'object' => 'checkout.session',
            'id' => 'cs_test_1',
            'mode' => 'payment',
            'payment_intent' => 'pi_test_1',
            'payment_status' => $statoPagamento,
            'amount_total' => 4000,
            'metadata' => ['order_id' => (string) $order->id],
        ];
    }

    #[Test]
    public function la_sessione_chiusa_senza_incasso_lascia_l_ordine_in_attesa(): void
    {
        $order = $this->ordineInAttesa();

        $this->evento('checkout.session.completed', $this->sessione($order, 'unpaid'))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->payment_id);
    }

    #[Test]
    public function la_sessione_pagata_conferma_l_ordine(): void
    {
        $order = $this->ordineInAttesa();

        $this->evento('checkout.session.completed', $this->sessione($order, 'paid'))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('pi_test_1', $order->payment_id);
    }

    #[Test]
    public function il_pagamento_differito_riuscito_conferma_l_ordine(): void
    {
        $order = $this->ordineInAttesa();

        $this->evento('checkout.session.completed', $this->sessione($order, 'unpaid'))->assertOk();
        $this->evento('checkout.session.async_payment_succeeded', $this->sessione($order, 'paid'))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('pi_test_1', $order->payment_id);
    }

    #[Test]
    public function il_pagamento_differito_fallito_annulla_l_ordine(): void
    {
        $order = $this->ordineInAttesa();

        $this->evento('checkout.session.async_payment_failed', $this->sessione($order, 'unpaid'))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    #[Test]
    public function l_ordine_con_pagamento_differito_in_corso_non_si_annulla_dopo_un_ora(): void
    {
        $order = $this->ordineInAttesa();
        $this->evento('checkout.session.completed', $this->sessione($order, 'unpaid'))->assertOk();

        $abbandonato = $this->ordineInAttesa();

        $this->travel(2)->hours();
        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $abbandonato->refresh()->status);
    }

    #[Test]
    public function la_contestazione_annota_l_ordine_e_avvisa_per_email(): void
    {
        $order = $this->ordineInAttesa();
        $order->forceFill(['status' => OrderStatus::Paid, 'payment_id' => 'pi_test_1', 'paid_at' => now()])->save();

        $disputa = [
            'object' => 'dispute',
            'id' => 'dp_test_1',
            'payment_intent' => 'pi_test_1',
            'amount' => 4000,
            'reason' => 'fraudulent',
            'evidence_details' => ['due_by' => now()->addDays(7)->getTimestamp()],
        ];

        $this->evento('charge.dispute.created', $disputa)->assertOk();
        // Stripe ripete l'evento: la nota non si duplica.
        $this->evento('charge.dispute.created', $disputa)->assertOk();

        $this->assertSame(1, substr_count((string) $order->refresh()->notes, 'dp_test_1'));
        $this->assertTrue(AvvisoTecnico::memoria()->has('avviso-tecnico:stripe-disputa:dp_test_1'));
    }

    #[Test]
    public function la_sessione_non_fissa_i_metodi_di_pagamento(): void
    {
        $order = $this->ordineInAttesa();

        (new StripePaymentService)->createSession($order);

        $this->assertArrayNotHasKey('payment_method_types', $this->richieste[0]['params']);
    }

    #[Test]
    public function il_rimborso_ripetuto_usa_la_stessa_chiave_di_idempotenza(): void
    {
        $order = $this->ordineInAttesa();
        $order->forceFill(['status' => OrderStatus::Paid, 'payment_id' => 'pi_test_1'])->save();

        (new StripePaymentService)->refund($order, 10.0);
        (new StripePaymentService)->refund($order, 10.0);

        $chiavi = array_map(
            fn (array $r): string => (string) collect($r['headers'])->first(fn (string $h): bool => str_starts_with($h, 'Idempotency-Key:')),
            $this->richieste,
        );

        $this->assertNotSame('', $chiavi[0]);
        $this->assertSame($chiavi[0], $chiavi[1]);
    }
}
