<?php

namespace Tests\Feature\Observability;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * Un secondo incasso sullo stesso ordine è denaro da rimborsare.
 *
 * Finora il caso veniva registrato in `shop_events` e annotato sull'ordine, e
 * lì si fermava: nessuno veniva avvisato, quindi il rimborso partiva solo se
 * qualcuno andava a cercare quella riga. Questo test blocca il ritorno al
 * silenzio.
 *
 * Le email si leggono dal mailer `array` di phpunit.xml, come in
 * AvvisoTecnicoTest: Mail::fake() rende inerte Mail::raw(), e con lui
 * l'avviso per email, che qui passerebbe inosservato.
 */
class PaymentReviewAlertTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.avvisi.email' => 'allarmi@example.com']);
        $this->configureFakePayPal();
    }

    /** @return list<string> */
    private function avvisiDaVerificare(Order $order): array
    {
        return AvvisoTecnicoTest::inviate()
            ->map(fn ($m) => $m->getOriginalMessage()->getSubject())
            ->filter(fn (string $oggetto) => $oggetto === "[Sito Savino] Ordine #{$order->order_number} da verificare")
            ->values()
            ->all();
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role->value])->save();

        return $user;
    }

    #[Test]
    public function un_doppio_incasso_avvisa_chi_puo_rimborsare(): void
    {
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);
        $shopManager = $this->userWithRole(UserRole::ShopManager);

        $order = Order::factory()->create();
        $order->forceFill([
            'status' => OrderStatus::Paid,
            'payment_id' => 'CAPTURE-OLD',
            'paid_at' => now(),
        ])->save();

        $this->fakePayPal($order->id, 'CAPTURE-NEW');
        $this->postWebhook()->assertOk();

        // Si conta solo questa notifica: la creazione dell'ordine ne genera già
        // altre (nuovo ordine, pagamento ricevuto) e un conteggio totale
        // passerebbe anche se l'avviso di revisione non partisse affatto.
        $title = "Ordine #{$order->order_number} da verificare";

        // Va a entrambi i ruoli: è materia di shop e di amministrazione.
        $this->assertSame(1, $superAdmin->notifications()->whereJsonContains('data->title', $title)->count());
        $this->assertSame(1, $shopManager->notifications()->whereJsonContains('data->title', $title)->count());

        // E per email: la campanella si vede solo entrando nel pannello.
        $this->assertCount(1, $this->avvisiDaVerificare($order));
    }

    #[Test]
    public function l_email_parte_dopo_il_commit(): void
    {
        // Il webhook chiama da dentro una transazione con la riga
        // dell'ordine bloccata: l'invio aspetta il commit.
        $order = Order::factory()->create();

        DB::transaction(function () use ($order): void {
            app(AdminNotificationService::class)->notifyPaymentNeedsReview($order, 'double_payment', 'Secondo incasso');

            $this->assertSame([], $this->avvisiDaVerificare($order));
        });

        $this->assertCount(1, $this->avvisiDaVerificare($order));
    }

    #[Test]
    public function su_rollback_l_email_non_parte(): void
    {
        $order = Order::factory()->create();

        try {
            DB::transaction(function () use ($order): void {
                app(AdminNotificationService::class)->notifyPaymentNeedsReview($order, 'double_payment', 'Secondo incasso');

                throw new RuntimeException('webhook annullato');
            });
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertSame([], $this->avvisiDaVerificare($order));
    }

    #[Test]
    public function un_pagamento_regolare_non_genera_avvisi(): void
    {
        // Il canale deve restare credibile: se si accendesse anche sugli
        // incassi normali, il primo doppio pagamento vero passerebbe inosservato.
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);

        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->fakePayPal($order->id, 'CAPTURE-1');
        $this->postWebhook()->assertOk();

        $this->assertSame(
            0,
            $superAdmin->notifications()
                ->whereJsonContains('data->title', "Ordine #{$order->order_number} da verificare")
                ->count(),
        );
        $this->assertSame([], $this->avvisiDaVerificare($order));
    }
}
