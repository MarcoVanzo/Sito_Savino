<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Jobs\InviaAcquistoAMeta;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ConsensoCookie;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MetaConversioni\MetaConversioniException;
use App\Services\Payments\PayPalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * La Conversions API di Meta: l'acquisto mandato dal server, solo per gli
 * ordini fatti con il consenso di marketing, con lo stesso event_id del pixel.
 */
class AcquistoMandatoAMetaDalServerTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    private const RIFERIMENTO = '7b0d6d0e-5a4f-4f4e-9b1e-1a2b3c4d5e6f';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'services.meta.capi.enabled' => true,
            'services.meta.capi.token' => 'token-di-prova',
            'services.meta.capi.test_event_code' => null,
        ]);
        SiteSetting::set('meta_pixel_id', '2048882385693445', 'analytics');
    }

    /**
     * Una scelta registrata come la registra il banner, dalla sua rotta. Con
     * `$versione` diversa si simula una scelta fatta su un'informativa vecchia.
     */
    private function consenso(bool $marketing, ?string $versione = null): void
    {
        $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => false,
            'marketing' => $marketing,
            'riferimento' => self::RIFERIMENTO,
        ])->assertSuccessful();

        if ($versione !== null) {
            ConsensoCookie::query()->where('riferimento', self::RIFERIMENTO)->toBase()->update(['versione' => $versione]);
        }
    }

    /** @return array<string, mixed> */
    private function datiCheckout(array $extra = []): array
    {
        return [
            'shipping_first_name' => 'Marco',
            'shipping_last_name' => 'Rossi',
            'shipping_street' => 'Via Roma 1',
            'shipping_city' => 'Firenze',
            'shipping_zip_code' => '50100',
            'shipping_province' => 'FI',
            'country' => 'IT',
            'billing_same_as_shipping' => true,
            'payment_gateway' => 'paypal',
            'privacy_accepted' => true,
            'phone' => '333 1234567',
            'codice_fiscale' => 'RSSMRC85M01D612H',
            ...$extra,
        ];
    }

    private function ordinaConPayPal(array $extra): Order
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 5, 'free_threshold' => null]);
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => Product::factory()->create(['stock' => 5, 'price' => 30])->id,
            'quantity' => 1,
        ]);

        $this->mock(PayPalPaymentService::class)
            ->shouldReceive('createSession')
            ->andReturn('https://www.paypal.com/checkoutnow?token=TOKEN-TEST');

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'User-Agent' => 'Mozilla/5.0 Prova'])
            ->post(route('shop.checkout.store'), $this->datiCheckout($extra))
            ->assertStatus(409);

        return Order::firstOrFail();
    }

    #[Test]
    public function il_checkout_fotografa_il_consenso_di_marketing_del_registro(): void
    {
        $this->consenso(marketing: true);

        $order = $this->ordinaConPayPal([
            'consenso_riferimento' => self::RIFERIMENTO,
            'meta_fbp' => 'fb.1.1700000000000.123456789',
            'meta_fbc' => 'fb.1.1700000000000.IwAR-abc_123',
        ]);

        $this->assertTrue($order->consenso_marketing);
        $this->assertSame(self::RIFERIMENTO, $order->consenso_cookie_riferimento);
        $this->assertSame('fb.1.1700000000000.123456789', $order->meta_fbp);
        $this->assertSame('fb.1.1700000000000.IwAR-abc_123', $order->meta_fbc);
        $this->assertSame('Mozilla/5.0 Prova', $order->meta_user_agent);
        $this->assertArrayNotHasKey('meta_fbp', $order->toArray());
    }

    #[Test]
    public function senza_consenso_valido_nel_registro_non_si_salva_niente_per_meta(): void
    {
        // La casella del browser non basta: vale l'ultima scelta registrata.
        $this->consenso(marketing: true);
        $this->consenso(marketing: false);

        $order = $this->ordinaConPayPal([
            'consenso_riferimento' => self::RIFERIMENTO,
            'meta_fbp' => 'fb.1.1700000000000.123456789',
        ]);

        $this->assertFalse($order->consenso_marketing);
        $this->assertNull($order->meta_fbp);
        $this->assertNull($order->meta_user_agent);
    }

    #[Test]
    public function un_consenso_su_un_informativa_vecchia_non_vale(): void
    {
        $this->consenso(marketing: true, versione: '2026-01-01');

        $order = $this->ordinaConPayPal(['consenso_riferimento' => self::RIFERIMENTO]);

        $this->assertFalse($order->consenso_marketing);
    }

    private function ordinePagabile(bool $consenso): Order
    {
        $order = Order::factory()->create(['guest_email' => ' Cliente@Example.com ', 'phone' => '333 1234567', 'country' => 'IT']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => Product::factory()->create(['stock' => 10])->id,
            'quantity' => 2, 'price_at_time_of_purchase' => 20,
        ]);
        $order->forceFill([
            'user_id' => null,
            'status' => OrderStatus::Pending,
            'payment_gateway' => PaymentGateway::PayPal,
            'total_price' => 40.00,
            'consenso_marketing' => $consenso,
            'meta_fbp' => $consenso ? 'fb.1.1700000000000.123456789' : null,
            'meta_user_agent' => $consenso ? 'Mozilla/5.0 Prova' : null,
        ])->save();

        return $order->refresh();
    }

    private function incassaConPayPal(Order $order, array $rispostaMeta = ['events_received' => 1]): void
    {
        $this->configureFakePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/PAYPAL-ORDER-1/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-1',
                        'status' => 'COMPLETED',
                        'amount' => ['value' => '40.00', 'currency_code' => 'EUR'],
                    ]]],
                ]],
            ]),
            'graph.facebook.com/*' => Http::response($rispostaMeta),
        ]);

        $evento = [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'PAYPAL-ORDER-1',
                'purchase_units' => [['reference_id' => $order->order_number, 'custom_id' => (string) $order->id]],
            ],
        ];

        // Due volte: PayPal ripete le notifiche, Meta deve ricevere un acquisto solo.
        $this->postJson('/api/webhooks/paypal', $evento)->assertOk();
        $this->postJson('/api/webhooks/paypal', $evento)->assertOk();
    }

    /** @return list<Request> */
    private function chiamateAMeta(): array
    {
        return Http::recorded(fn (Request $richiesta) => str_contains($richiesta->url(), 'graph.facebook.com'))
            ->map(fn (array $coppia) => $coppia[0])
            ->values()
            ->all();
    }

    #[Test]
    public function il_pagamento_confermato_manda_il_purchase_con_l_event_id_del_pixel(): void
    {
        $order = $this->ordinePagabile(consenso: true);

        $this->incassaConPayPal($order);

        $chiamate = $this->chiamateAMeta();
        $this->assertCount(1, $chiamate);
        $this->assertStringContainsString('/2048882385693445/events', $chiamate[0]->url());
        $this->assertStringContainsString('access_token=token-di-prova', $chiamate[0]->url());

        $evento = $chiamate[0]->data()['data'][0];
        $this->assertSame('Purchase', $evento['event_name']);
        $this->assertSame('purchase-'.$order->order_number, $evento['event_id']);
        $this->assertSame('website', $evento['action_source']);
        $this->assertSame([hash('sha256', 'cliente@example.com')], $evento['user_data']['em']);
        $this->assertSame([hash('sha256', '393331234567')], $evento['user_data']['ph']);
        $this->assertSame('fb.1.1700000000000.123456789', $evento['user_data']['fbp']);
        $this->assertSame('Mozilla/5.0 Prova', $evento['user_data']['client_user_agent']);
        $this->assertArrayNotHasKey('fbc', $evento['user_data']);
        $this->assertSame(40.0, (float) $evento['custom_data']['value']);
        $this->assertSame('EUR', $evento['custom_data']['currency']);
        $this->assertSame(2, $evento['custom_data']['num_items']);
        $this->assertNotNull($order->refresh()->meta_acquisto_inviato_at);
    }

    #[Test]
    public function senza_consenso_sull_ordine_meta_non_riceve_niente(): void
    {
        $order = $this->ordinePagabile(consenso: false);

        $this->incassaConPayPal($order);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertCount(0, $this->chiamateAMeta());
    }

    #[Test]
    public function spenta_per_impostazione_non_accoda_niente(): void
    {
        config(['services.meta.capi.enabled' => false]);
        Queue::fake();

        InviaAcquistoAMeta::seServe($this->ordinePagabile(consenso: true));

        Queue::assertNothingPushed();
    }

    #[Test]
    public function un_rifiuto_di_meta_fa_ritentare_il_job_e_non_segna_l_ordine(): void
    {
        $order = $this->ordinePagabile(consenso: true);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400)]);

        try {
            (new InviaAcquistoAMeta($order))->handle();
            $this->fail('Il job doveva fallire per essere ritentato.');
        } catch (MetaConversioniException $e) {
            $this->assertStringContainsString('Invalid parameter', $e->getMessage());
        }

        $this->assertNull($order->refresh()->meta_acquisto_inviato_at);
        $this->assertSame(5, (new InviaAcquistoAMeta($order))->tries);
    }
}
