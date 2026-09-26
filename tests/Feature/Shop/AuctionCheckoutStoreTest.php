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
use App\Services\Payments\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Il modulo con cui il vincitore di un'asta paga il lotto.
 *
 * È l'unico punto in cui un'asta diventa un ordine, e non era coperto: i
 * controlli su chi può pagare, entro quando e con quali dati vivevano dentro un
 * metodo solo, senza nessun test a dire cosa devono fare.
 */
class AuctionCheckoutStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Il vincitore sceglie fra i metodi con le credenziali: qui Stripe,
        // i casi con PayPal stanno in AuctionCheckoutPayPalTest.
        config(['services.stripe.secret' => 'sk_test_finto']);
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
            'payment_gateway' => 'stripe',
            ...$sovrascrivi,
        ];
    }

    private function astaVinta(User $winner, string $token, ?\DateTimeInterface $deadline = null): Auction
    {
        // Il lotto ha una giacenza esplicita: qui serve sapere che c'è merce
        // da scaricare, e leggerlo nel test vale più che fidarsi del minimo
        // della fabbrica.
        $auction = Auction::factory()->ended()->create([
            'current_bid' => 100,
            'product_id' => Product::factory()->create(['stock' => 5])->id,
        ]);

        $auction->forceFill([
            'winner_user_id' => $winner->id,
            'winner_checkout_token' => $token,
            'winner_checkout_deadline' => $deadline ?? now()->addHours(48),
        ])->save();

        return $auction;
    }

    public function test_un_token_inesistente_non_esiste(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('shop.auction-checkout.store', ['token' => Str::uuid()->toString()]), $this->datiValidi())
            ->assertNotFound();
    }

    public function test_solo_il_vincitore_puo_pagare(): void
    {
        $token = Str::uuid()->toString();
        $this->astaVinta(User::factory()->create(), $token);

        $this->actingAs(User::factory()->create())
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertForbidden();
    }

    public function test_oltre_il_termine_si_torna_alla_pagina_con_un_avviso(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $this->astaVinta($winner, $token, now()->subHour());

        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect(route('shop.auction-checkout.show', ['token' => $token]))
            ->assertSessionHas('error');
    }

    public function test_per_l_italia_il_cap_e_di_cinque_cifre(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $this->astaVinta($winner, $token);

        $this->actingAs($winner)
            ->post(
                route('shop.auction-checkout.store', ['token' => $token]),
                $this->datiValidi(['shipping_zip_code' => '5001'])
            )
            ->assertSessionHasErrors('shipping_zip_code');
    }

    public function test_per_l_italia_il_codice_fiscale_e_obbligatorio(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $this->astaVinta($winner, $token);

        $this->actingAs($winner)
            ->post(
                route('shop.auction-checkout.store', ['token' => $token]),
                $this->datiValidi(['codice_fiscale' => null])
            )
            ->assertSessionHasErrors('codice_fiscale');
    }

    public function test_un_paese_senza_zona_di_spedizione_non_si_serve(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $this->astaVinta($winner, $token);

        $this->actingAs($winner)
            ->post(
                route('shop.auction-checkout.store', ['token' => $token]),
                $this->datiValidi(['country' => 'JP', 'codice_fiscale' => null, 'shipping_zip_code' => '100-0001'])
            )
            ->assertSessionHasErrors('country');
    }

    public function test_un_ordine_gia_pagato_porta_alla_conferma_senza_ricrearlo(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $auction = $this->astaVinta($winner, $token);

        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7.9, 'free_threshold' => 1000]);

        $order = Order::factory()->create([
            'user_id' => $winner->id,
            'payment_gateway' => PaymentGateway::Stripe,
            'paid_at' => now(),
        ]);
        $order->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Processing])->save();

        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect(route('shop.auction-checkout.success', ['token' => $token]));

        $this->assertSame(1, Order::where('auction_id', $auction->id)->count());
    }

    public function test_il_primo_pagamento_crea_ordine_riga_e_movimento_di_magazzino(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $auction = $this->astaVinta($winner, $token);

        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7.9, 'free_threshold' => 1000]);

        $stripe = Mockery::mock(StripePaymentService::class);
        $stripe->shouldReceive('createSession')->once()->andReturn('https://checkout.stripe.test/sessione');
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect('https://checkout.stripe.test/sessione');

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame($winner->id, $order->user_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        // Offerta del vincitore piu' la spedizione.
        $this->assertEqualsWithDelta(107.9, (float) $order->total_price, 0.01);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 1]);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'quantity' => -1]);
        // Il testo delle condizioni accettate è fotografato sull'ordine.
        $this->assertNotNull($order->condizioni_impronta);
        $this->assertDatabaseHas('versioni_condizioni', ['impronta' => $order->condizioni_impronta]);
    }

    public function test_un_checkout_abbandonato_riusa_l_ordine_invece_di_duplicarlo(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $auction = $this->astaVinta($winner, $token);

        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7.9, 'free_threshold' => 1000]);

        $order = Order::factory()->create([
            'user_id' => $winner->id,
            'payment_gateway' => PaymentGateway::Stripe,
            'paid_at' => null,
            'phone' => '000',
        ]);
        $order->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Pending])->save();

        $stripe = Mockery::mock(StripePaymentService::class);
        $stripe->shouldReceive('createSession')->once()->andReturn('https://checkout.stripe.test/sessione');
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect('https://checkout.stripe.test/sessione');

        $this->assertSame(1, Order::where('auction_id', $auction->id)->count());
        $this->assertSame('3331234567', $order->fresh()->phone);
    }

    public function test_la_spedizione_del_lotto_segue_la_fascia_di_peso(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $auction = $this->astaVinta($winner, $token);
        // Un lotto pesante: sei chili stanno oltre la seconda fascia.
        $auction->product->update(['weight' => 6]);

        ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.9,
            'free_threshold' => 1000,
            'weight_rates' => [
                ['max_weight' => 2, 'rate' => 5.9],
                ['max_weight' => 5, 'rate' => 9.9],
                ['max_weight' => null, 'rate' => 19.9],
            ],
        ]);

        $stripe = Mockery::mock(StripePaymentService::class);
        $stripe->shouldReceive('createSession')->once()->andReturn('https://checkout.stripe.test/sessione');
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect('https://checkout.stripe.test/sessione');

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        // 100 di offerta piu' la fascia oltre i 5 kg, non la tariffa base.
        $this->assertEqualsWithDelta(119.9, (float) $order->total_price, 0.01);
    }

    public function test_un_lotto_senza_peso_in_scheda_usa_il_ripiego(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $auction = $this->astaVinta($winner, $token);
        $auction->product->update(['weight' => null]);

        SiteSetting::set('shop.default_item_weight_kg', '0.5');

        ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.9,
            'free_threshold' => 1000,
            'weight_rates' => [
                ['max_weight' => 2, 'rate' => 5.9],
                ['max_weight' => null, 'rate' => 19.9],
            ],
        ]);

        $stripe = Mockery::mock(StripePaymentService::class);
        $stripe->shouldReceive('createSession')->once()->andReturn('https://checkout.stripe.test/sessione');
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect('https://checkout.stripe.test/sessione');

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        // Mezzo chilo: la prima fascia, non quella "da lì in su".
        $this->assertEqualsWithDelta(105.9, (float) $order->total_price, 0.01);
    }

    public function test_la_pagina_passa_al_client_le_fasce_ordinate_e_il_peso_del_collo(): void
    {
        $winner = User::factory()->create();
        $token = Str::uuid()->toString();
        $auction = $this->astaVinta($winner, $token);
        $auction->product->update(['weight' => 6]);

        // Fasce scritte fuori ordine nel pannello: al client arrivano ordinate,
        // perché è nell'ordine che sceglie la tariffa.
        ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.9,
            'free_threshold' => 1000,
            'weight_rates' => [
                ['max_weight' => null, 'rate' => 19.9],
                ['max_weight' => 2, 'rate' => 5.9],
            ],
        ]);

        $response = $this->actingAs($winner)
            ->get(route('shop.auction-checkout.show', ['token' => $token]))
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertEqualsWithDelta(6.0, (float) $props['pesoDelCollo'], 0.001);
        $this->assertSame(
            [['max_weight' => 2.0, 'rate' => 5.9], ['max_weight' => null, 'rate' => 19.9]],
            $props['shippingZones'][0]['weight_rates'],
        );
    }
}
