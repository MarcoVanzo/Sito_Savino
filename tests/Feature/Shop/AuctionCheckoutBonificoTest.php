<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Mail\OrderConfirmation;
use App\Models\Auction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il vincitore di un'asta paga con bonifico (richiesta della società,
 * 30/09/2026).
 *
 * Il bonifico non sta nelle 48 ore del termine: sceglierlo sposta il termine
 * del vincitore alla scadenza del bonifico, contata dalla creazione
 * dell'ordine come nello shop. Senza, lo scheduler annullava l'ordine e
 * passava il lotto al secondo offerente con i soldi in viaggio.
 */
class AuctionCheckoutBonificoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.stripe.secret' => null, 'services.paypal.client_id' => null]);
        SiteSetting::set('shop.bank_transfer_expiry_days', '5');
        SiteSetting::set('shop.bank_transfer_iban', 'IT00X0000000000000000000000');

        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7.9, 'free_threshold' => 1000]);
    }

    /** @return array<string, mixed> */
    private function datiValidi(): array
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
            'payment_gateway' => 'bank_transfer',
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

    private function scegliIlBonifico(User $winner, string $token): void
    {
        $this->actingAs($winner)
            ->post(route('shop.auction-checkout.store', ['token' => $token]), $this->datiValidi())
            ->assertRedirect(route('shop.auction-checkout.success', ['token' => $token]));
    }

    #[Test]
    public function il_bonifico_crea_l_ordine_e_sposta_il_termine_alla_scadenza_del_bonifico(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        $this->freezeSecond();

        $this->scegliIlBonifico($winner, $token);

        $order = Order::where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame(PaymentGateway::BankTransfer, $order->payment_gateway);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertTrue($auction->fresh()->winner_checkout_deadline->equalTo(now()->addDays(5)));

        Mail::assertQueued(OrderConfirmation::class, fn ($mail) => $mail->hasTo($winner->email));
    }

    #[Test]
    public function la_conferma_mostra_le_coordinate_del_bonifico(): void
    {
        [$winner, , $token] = $this->astaVinta();
        $this->scegliIlBonifico($winner, $token);

        $props = $this->actingAs($winner)
            ->get(route('shop.auction-checkout.success', ['token' => $token]))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('IT00X0000000000000000000000', $props['bonifico']['iban']);
        $this->assertNotNull($props['bonifico']['entro']);
    }

    #[Test]
    public function il_lotto_non_passa_al_secondo_offerente_prima_della_scadenza_del_bonifico(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        $this->scegliIlBonifico($winner, $token);

        // Passate le 48 ore dell'asta, il bonifico ha ancora tempo.
        $this->travel(3)->days();
        app(AuctionService::class)->checkWinnerPayments();
        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame($winner->id, $auction->fresh()->winner_user_id);
        $this->assertSame(OrderStatus::Pending, Order::where('auction_id', $auction->id)->firstOrFail()->status);

        // Scaduto il bonifico, l'ordine si annulla e il turno finisce.
        $this->travel(3)->days();
        app(AuctionService::class)->checkWinnerPayments();

        $this->assertSame(OrderStatus::Cancelled, Order::where('auction_id', $auction->id)->firstOrFail()->status);
        $this->assertNotSame($winner->id, $auction->fresh()->winner_user_id);
    }

    #[Test]
    public function reinviare_il_modulo_non_allunga_il_termine(): void
    {
        [$winner, $auction, $token] = $this->astaVinta();
        $this->freezeSecond();
        $this->scegliIlBonifico($winner, $token);
        $termine = $auction->fresh()->winner_checkout_deadline;

        $this->travel(2)->days();
        $this->scegliIlBonifico($winner, $token);

        $this->assertTrue($auction->fresh()->winner_checkout_deadline->equalTo($termine));
        // Stesso bonifico, stessa email: non si rimanda a ogni invio.
        Mail::assertQueued(OrderConfirmation::class, 1);
    }

    #[Test]
    public function l_annullo_dei_bonifici_dello_shop_segue_i_giorni_del_pannello(): void
    {
        // L'email di conferma promette `shop.bank_transfer_expiry_days`
        // giorni: l'annullo non può arrivare prima né dopo.
        $order = Order::factory()->create(['payment_gateway' => PaymentGateway::BankTransfer, 'payment_id' => null]);
        $order->forceFill(['status' => OrderStatus::Pending, 'created_at' => now()->subDays(4)])->save();

        $this->artisan('order:check-unpaid')->assertSuccessful();
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        $this->travel(1)->days();
        $this->artisan('order:check-unpaid')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }
}
