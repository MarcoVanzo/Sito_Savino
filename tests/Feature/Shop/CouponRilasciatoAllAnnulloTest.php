<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un ordine mai pagato e poi annullato restituisce il coupon.
 *
 * Il checkout registra l'uso e incrementa `used_count` subito, prima del
 * pagamento: un checkout abbandonato consumava per sempre un coupon a uso
 * singolo (o l'unico uso concesso a quel cliente), e il cliente che tornava
 * a comprare se lo vedeva rifiutare.
 */
class CouponRilasciatoAllAnnulloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /** @return array{Coupon, Order} */
    private function ordineConCoupon(int $usati = 1): array
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $coupon->forceFill(['used_count' => $usati])->save();

        $order = Order::factory()->create(['coupon_id' => $coupon->id]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        CouponUsage::factory()->create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
        ]);

        return [$coupon, $order->refresh()];
    }

    #[Test]
    public function l_annullo_di_un_ordine_non_pagato_libera_il_coupon(): void
    {
        [$coupon, $order] = $this->ordineConCoupon();

        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        $this->assertSame(0, (int) $coupon->fresh()->used_count);
        $this->assertDatabaseMissing('coupon_usages', ['order_id' => $order->id]);
    }

    #[Test]
    public function il_rilascio_non_si_ripete_e_non_scende_sotto_zero(): void
    {
        [$coupon, $order] = $this->ordineConCoupon(usati: 0);

        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        // Un secondo cambio di stato che ripassa da Annullato.
        $order->forceFill(['status' => OrderStatus::Pending])->save();
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        $this->assertSame(0, (int) $coupon->fresh()->used_count);
    }

    #[Test]
    public function l_annullo_di_un_ordine_pagato_non_restituisce_il_coupon(): void
    {
        [$coupon, $order] = $this->ordineConCoupon();
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now(), 'payment_id' => 'pi_pagato'])->save();

        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        $this->assertSame(1, (int) $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_usages', ['order_id' => $order->id]);
    }

    #[Test]
    public function l_annullamento_automatico_libera_il_coupon(): void
    {
        $this->freezeTime();
        [$coupon, $order] = $this->ordineConCoupon();
        $order->forceFill(['payment_gateway' => 'stripe'])->save();

        $this->travel(2)->hours();
        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, (int) $coupon->fresh()->used_count);
    }
}
