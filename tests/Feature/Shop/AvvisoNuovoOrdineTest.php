<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\UserRole;
use App\Filament\Pages\Settings\ShopSettingsPage;
use App\Mail\NuovoOrdineAllaSocieta;
use App\Models\Auction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AvvisoNuovoOrdine;
use App\Services\ReceiptService;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * A ogni acquisto la società riceve un'email, agli indirizzi scelti nel
 * pannello (richiesta della società, 01/10/2026). Con la ricevuta allegata.
 */
class AvvisoNuovoOrdineTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function ordinePayPalInAttesa(): Order
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => Product::factory()->create(['stock' => 10])->id,
            'quantity' => 2, 'price_at_time_of_purchase' => 20,
        ]);
        $order->forceFill([
            'status' => OrderStatus::Pending,
            'payment_gateway' => PaymentGateway::PayPal,
            'total_price' => 40.00,
        ])->save();

        return $order->refresh();
    }

    private function incassaConPayPal(Order $order): void
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
        ]);

        $evento = [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'PAYPAL-ORDER-1',
                'purchase_units' => [['reference_id' => $order->order_number, 'custom_id' => (string) $order->id]],
            ],
        ];

        // Due volte: PayPal ripete le notifiche, la società non deve ricevere doppioni.
        $this->postJson('/api/webhooks/paypal', $evento)->assertOk();
        $this->postJson('/api/webhooks/paypal', $evento)->assertOk();
    }

    #[Test]
    public function un_pagamento_incassato_avvisa_gli_indirizzi_della_societa_una_volta_sola(): void
    {
        SiteSetting::set(AvvisoNuovoOrdine::IMPOSTAZIONE, 'shop@example.com, Ordini@Example.com');
        $order = $this->ordinePayPalInAttesa();

        $this->incassaConPayPal($order);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        Mail::assertQueued(NuovoOrdineAllaSocieta::class, 1);
        Mail::assertQueued(NuovoOrdineAllaSocieta::class, fn ($mail) => $mail->hasTo('shop@example.com')
            && $mail->hasTo('ordini@example.com')
            && $mail->order->is($order));
    }

    #[Test]
    public function senza_indirizzi_impostati_non_parte_niente(): void
    {
        $order = $this->ordinePayPalInAttesa();

        $this->incassaConPayPal($order);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        Mail::assertNotQueued(NuovoOrdineAllaSocieta::class);
    }

    #[Test]
    public function il_bonifico_di_un_asta_avvisa_la_societa_subito(): void
    {
        SiteSetting::set(AvvisoNuovoOrdine::IMPOSTAZIONE, 'shop@example.com');
        SiteSetting::set('shop.bank_transfer_iban', 'IT00X0000000000000000000000');
        config(['services.stripe.secret' => null, 'services.paypal.client_id' => null]);
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7.9, 'free_threshold' => 1000]);

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

        $this->actingAs($winner)->post(route('shop.auction-checkout.store', ['token' => $token]), [
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
        ])->assertRedirect();

        Mail::assertQueued(NuovoOrdineAllaSocieta::class, fn ($mail) => $mail->hasTo('shop@example.com'));
    }

    #[Test]
    public function l_email_si_compone_con_la_ricevuta_allegata(): void
    {
        $order = $this->ordinePayPalInAttesa();
        $order->forceFill(['paid_at' => now(), 'status' => OrderStatus::Paid])->save();

        $mail = new NuovoOrdineAllaSocieta($order->refresh());

        $mail->assertSeeInHtml($order->order_number);
        $mail->assertSeeInHtml('/admin/shop/ordini/'.$order->id.'/edit');
        $mail->assertHasSubject('Nuovo ordine '.$order->order_number.' — € 40,00');

        $allegati = $mail->attachments();
        $this->assertCount(1, $allegati);
        $this->assertSame('ricevuta-'.$order->order_number.'.pdf', $allegati[0]->as);
        $this->assertStringStartsWith('%PDF', $allegati[0]->attachWith(fn () => '', fn (\Closure $dati) => $dati()));
    }

    #[Test]
    public function il_codice_fiscale_sta_nell_avviso_e_nella_ricevuta(): void
    {
        $order = $this->ordinePayPalInAttesa();
        $order->forceFill(['codice_fiscale' => 'RSSNNA85M41D612K'])->save();

        (new NuovoOrdineAllaSocieta($order->refresh()))->assertSeeInHtml('RSSNNA85M41D612K');

        $order->load('items', 'user');
        $this->assertStringContainsString(
            'Codice fiscale RSSNNA85M41D612K',
            view('pdf.receipt', ['order' => $order])->render()
        );
    }

    #[Test]
    public function la_ricevuta_usa_montserrat_incorporato(): void
    {
        $order = $this->ordinePayPalInAttesa();

        $pdf = app(ReceiptService::class)->generate($order);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('Montserrat-Bold', $pdf);
        $this->assertStringContainsString('Montserrat-Regular', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
    }

    #[Test]
    public function gli_indirizzi_si_leggono_separati_da_virgola_senza_doppioni(): void
    {
        $this->assertSame(
            ['a@example.com', 'b@example.com'],
            AvvisoNuovoOrdine::destinatari(" a@example.com, B@example.com;A@example.com  non-un-indirizzo \n"),
        );
        $this->assertSame([], AvvisoNuovoOrdine::destinatari(''));
    }

    #[Test]
    public function il_pannello_rifiuta_un_indirizzo_sbagliato_e_salva_quelli_buoni(): void
    {
        $this->seed(ShopSettingsSeeder::class);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin])->save();

        Livewire::actingAs($admin->refresh())
            ->test(ShopSettingsPage::class)
            ->set('data.shop.order_notification_emails', 'shop@example.com, sbagliato')
            ->call('save')
            ->assertHasErrors(['data.shop.order_notification_emails']);

        Livewire::actingAs($admin)
            ->test(ShopSettingsPage::class)
            ->set('data.shop.order_notification_emails', 'shop@example.com, ordini@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['shop@example.com', 'ordini@example.com'], AvvisoNuovoOrdine::destinatari());
        $this->assertTrue(filter_var(SiteSetting::get('shop.enabled'), FILTER_VALIDATE_BOOLEAN));
    }
}
