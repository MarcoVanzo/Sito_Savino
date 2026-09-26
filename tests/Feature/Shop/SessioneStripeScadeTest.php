<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentGateway;
use App\Models\Order;
use App\Services\Payments\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * La sessione Stripe di un ordine dello shop scade prima che l'ordine venga
 * annullato.
 *
 * Senza `expires_at` la sessione durava 24 ore mentre `order:check-unpaid`
 * annulla dopo un'ora: il cliente poteva pagare un ordine gia' annullato,
 * con la merce tornata a scaffale.
 */
class SessioneStripeScadeTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $inviati = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.secret', 'sk_test_finto');

        $test = $this;
        ApiRequestor::setHttpClient(new class($test) implements ClientInterface
        {
            public function __construct(private SessioneStripeScadeTest $test) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->test->registra($params);

                return [json_encode(['id' => 'cs_test_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/cs_test_1']), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $params */
    public function registra(array $params): void
    {
        $this->inviati = $params;
    }

    #[Test]
    public function la_sessione_dello_shop_scade_prima_dell_annullamento_automatico(): void
    {
        $this->freezeTime();

        $order = Order::factory()->create(['payment_gateway' => PaymentGateway::Stripe, 'total_price' => 30]);

        (new StripePaymentService)->createSession($order);

        $this->assertArrayHasKey('expires_at', $this->inviati);

        $scade = (int) $this->inviati['expires_at'];

        // Almeno 30 minuti (minimo di Stripe), prima dell'ora dell'annullamento.
        $this->assertGreaterThanOrEqual(now()->addMinutes(30)->getTimestamp(), $scade);
        $this->assertLessThan(now()->addHour()->getTimestamp(), $scade);
    }
}
