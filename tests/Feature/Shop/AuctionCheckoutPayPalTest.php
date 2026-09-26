<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Auction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * Il vincitore di un'asta paga con PayPal.
 *
 * Il checkout delle aste passava solo da Stripe, che in produzione non ha
 * chiavi: con le aste accese nessun vincitore poteva pagare. L'ordine
 * dell'asta è un Order come gli altri, quindi l'incasso segue le stesse due
 * strade dello shop — ritorno sulla pagina di conferma e webhook — e qui si
 * verifica che le percorra davvero.
 */
class AuctionCheckoutPayPalTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    private const APPROVAZIONE = 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ASTA-1';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->configureFakePayPal();
        // Come in produzione oggi: PayPal sì, Stripe senza chiavi.
        config(['services.stripe.secret' => null]);

        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7.9, 'free_threshold' => 1000]);
    }

    /** @return array<string, mixed> */
    private function datiValidi(array $sovrascrivi = []): array
    {
        return [
            'shipping_first_name' => 'Anna',
            'shipping_last_name' => 'Rossi',
            'shipping_street' => 'Via Rialdoli 1',
            'shipping_city' => 'Scandicci',
            'shipping_zip_code' => '50018',
            'shipping_province' => 'FI',
            'country' => 'IT',
            'phone' => '3331234567',
            'codice_fiscale' => 'RSSNNA85M41D612K',
            'billing_same_as_shipping' => true,
            'privacy_accepted' => true,
            'payment_gateway' => 'paypal',
            ...$sovrascrivi,
        ];
    }

    /** @return array{0: User, 1: Auction, 2: string} */
    private function astaVinta(): array
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();

        $auction = Auction::factory()->ended()->create([
            'current_bid' => 100,
            'product_id' => Product::factory()->create(['stock' => 5])->id,
        ]);

        $auction->forceFill([
            'winner_user_id' => $winner->id,
            'winner_checkout_token' => $token,
            'winner_checkout_deadline' => now()->addHours(48),
        ])->save();

        return [$winner, $auction, $token];
    }

    private function fakeCreazioneOrdinePayPal(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ASTA-1',
                'status' => 'CREATED',
                'links' => [['rel' => 'approve', 'href' => self::APPROVAZIONE]],
            ]),
        ]);
    }

    private function ordineDellAsta(User $winner, Auction $auction, PaymentGateway $gateway): Order
    {
        $order = Order::factory()->create([
            'user_id' => $winner->id,
            'payment_gateway' => $gateway,
            'paid_at' => null,
        ]);

        $order->forceFill([
            'auction_id' => $auction->id,
            'status' => OrderStatus::Pending,
            'payment_id' => null,
            'total_price' => 107.90,
        ])->save();

        return $order->refresh();
    }

    /** @return list<string> */
    private function metodiOffertiDallaPagina(string $token, User $winner): array
    {
        $props = $this->actingAs($winner)
            ->get(route('shop.auction-checkout.show', ['token' => $token]))
            ->assertOk()
            ->viewData('page')['props'];

        return array_column($props['paymentGateways'], 'value');
    }

    #[Test]
    public function la_pagina_offre_solo_i_metodi_con_le_credenziali_e_mai_il_bonifico(): void
    {
        [$winner, , $token] = $this->astaVinta();

        $this->assertSame(['paypal'], $this->metodiOffertiDallaPagina($token, $winner));

        // Arrivano le chiavi di Stripe: compare anche la carta.
        config(['services.stripe.secret' => 'sk_test_finto']);

        $this->assertSame(['stripe', 'paypal'], $this->metodiOffertiDallaPagina($token, $winner));
    }

    #[Test]
    public function la_pagina_rispetta_i_metodi_attivi_dal_pannello(): void
    {
        [$winner, , $token] = $this->astaVinta();
        config(['services.stripe.secret' => 'sk_test_finto']);

        SiteSetting::set('shop.active_payment_gateways', 'stripe,bank_transfer');

        $this->assertSame(['stripe'], $this->metodiOffertiDallaPagina($token, $winner));
    }

    #[Test]
    public function il_vincitore_esce_verso_paypal_con_inertia_location(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        $this->fakeCreazioneOrdinePayPal();

        $this->actingAs($winner)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', self::APPROVAZIONE);

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame(PaymentGateway::PayPal, $order->payment_gateway);
        $this->assertSame(OrderStatus::Pending, $order->status);

        // Si torna sulla conferma dello shop, dove si incassa; l'annullo
        // riporta all'asta, che controlla il termine prima di riaprire.
        Http::assertSent(function ($request) use ($order, $token) {
            if (! str_ends_with($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            $contesto = $request->data()['application_context'];
            $unita = $request->data()['purchase_units'][0];

            return $contesto['return_url'] === route('shop.checkout.success', ['orderToken' => $order->order_token])
                && $contesto['cancel_url'] === route('shop.auction-checkout.cancel', ['token' => $token])
                && $unita['custom_id'] === (string) $order->id
                && $unita['amount']['value'] === '107.90';
        });
    }

    #[Test]
    public function un_metodo_non_configurato_viene_rifiutato_senza_creare_l_ordine(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();

        foreach (['stripe', 'bank_transfer'] as $metodo) {
            $this->actingAs($winner)
                ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi(['payment_gateway' => $metodo]))
                ->assertSessionHasErrors('payment_gateway');
        }

        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
    }

    #[Test]
    public function il_ritorno_da_paypal_incassa_l_ordine_dell_asta(): void
    {
        [$winner, $auction] = $this->astaVinta();
        $order = $this->ordineDellAsta($winner, $auction, PaymentGateway::PayPal);

        $unita = [
            'reference_id' => $order->order_number,
            'custom_id' => (string) $order->id,
        ];

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders/PAYPAL-ASTA-1/capture' => Http::response([
                'id' => 'PAYPAL-ASTA-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    ...$unita,
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-ASTA',
                        'status' => 'COMPLETED',
                        'amount' => ['value' => '107.90', 'currency_code' => 'EUR'],
                    ]]],
                ]],
            ]),
            '*/v2/checkout/orders/PAYPAL-ASTA-1' => Http::response([
                'id' => 'PAYPAL-ASTA-1',
                'status' => 'APPROVED',
                'purchase_units' => [$unita],
            ]),
        ]);

        $this->actingAs($winner)
            ->get($order->successUrl().'?token=PAYPAL-ASTA-1&PayerID=PAYER1')
            ->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-ASTA', $order->payment_id);

        // Pagato, il checkout dell'asta porta alla sua conferma.
        $this->actingAs($winner)
            ->get(route('shop.auction-checkout.show', ['token' => $auction->winner_checkout_token]))
            ->assertRedirect(route('shop.auction-checkout.success', ['token' => $auction->winner_checkout_token]));
    }

    #[Test]
    public function il_webhook_risolve_l_ordine_dell_asta_ed_e_idempotente(): void
    {
        [$winner, $auction] = $this->astaVinta();
        $order = $this->ordineDellAsta($winner, $auction, PaymentGateway::PayPal);

        $this->fakePayPal($order->id, 'CAPTURE-WEBHOOK-ASTA');

        $this->postWebhook()->assertOk();
        // PayPal ripete la notifica: nessun secondo incasso, nessun errore.
        $this->postWebhook()->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-WEBHOOK-ASTA', $order->payment_id);
        $this->assertSame($auction->id, $order->auction_id);
    }

    #[Test]
    public function un_ordine_abbandonato_su_paypal_si_riapre_su_paypal(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        $order = $this->ordineDellAsta($winner, $auction, PaymentGateway::PayPal);
        $this->fakeCreazioneOrdinePayPal();

        $this->actingAs($winner)
            ->get(route('shop.auction-checkout.show', ['token' => $token]))
            ->assertRedirect(self::APPROVAZIONE);

        $this->assertSame(1, Order::where('auction_id', $auction->id)->count());
        $this->assertNull($order->refresh()->payment_id);
    }

    #[Test]
    public function un_ordine_aperto_con_un_metodo_non_piu_offerto_rimostra_il_modulo(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        // Aperto con la carta quando Stripe aveva le chiavi, che ora non ha.
        $this->ordineDellAsta($winner, $auction, PaymentGateway::Stripe);
        $this->fakeCreazioneOrdinePayPal();

        $this->actingAs($winner)
            ->get(route('shop.auction-checkout.show', ['token' => $token]))
            ->assertOk();

        // Scegliendo PayPal si riusa lo stesso ordine, col metodo nuovo.
        $this->actingAs($winner)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', self::APPROVAZIONE);

        $ordini = Order::where('auction_id', $auction->id)->get();

        $this->assertCount(1, $ordini);
        $this->assertSame(PaymentGateway::PayPal, $ordini->first()->payment_gateway);
    }

    #[Test]
    public function il_riprova_dello_shop_rimanda_l_ordine_dell_asta_al_suo_checkout(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        $order = $this->ordineDellAsta($winner, $auction, PaymentGateway::PayPal);

        Http::fake();

        $this->actingAs($winner)
            ->post(route('shop.checkout.retry', ['orderToken' => $order->order_token]))
            ->assertRedirect(route('shop.auction-checkout.show', ['token' => $token]));

        // Nessuna sessione aperta da qui: la riapre il checkout dell'asta,
        // dopo aver guardato il termine.
        Http::assertNothingSent();
    }
}
