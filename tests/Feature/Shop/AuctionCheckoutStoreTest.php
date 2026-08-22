<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Auction;
use App\Models\Order;
use App\Models\ShippingZone;
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
            ...$sovrascrivi,
        ];
    }

    private function astaVinta(User $winner, string $token, ?\DateTimeInterface $deadline = null): Auction
    {
        $auction = Auction::factory()->ended()->create(['current_bid' => 100]);

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
}
