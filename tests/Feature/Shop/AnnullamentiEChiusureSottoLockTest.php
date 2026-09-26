<?php

namespace Tests\Feature\Shop;

use App\Enums\AuctionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Annullamenti e chiusure automatiche decidono sul dato riletto sotto lock.
 *
 * `order:check-unpaid`, il turno scaduto del vincitore e la chiusura delle
 * aste lavoravano sulla copia letta all'inizio del giro: un pagamento (o un
 * rilancio che allunga l'asta) arrivato nel frattempo veniva sovrascritto.
 * Per simulare la corsa, il pagamento si scrive direttamente in archivio
 * appena il comando ha letto il record.
 */
class AnnullamentiEChiusureSottoLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    /**
     * Alla prima lettura del record, qualcun altro lo aggiorna in archivio.
     *
     * @param  class-string  $modello
     * @param  array<string, mixed>  $valori
     */
    private function nelFrattempo(string $modello, int $id, array $valori): void
    {
        $fatto = false;

        Event::listen("eloquent.retrieved: {$modello}", function ($record) use (&$fatto, $id, $valori) {
            if ($fatto || (int) $record->getKey() !== $id) {
                return;
            }

            $fatto = true;
            DB::table($record->getTable())->where('id', $id)->update($valori);
        });
    }

    #[Test]
    public function l_annullamento_automatico_non_sovrascrive_un_pagamento_appena_arrivato(): void
    {
        $order = Order::factory()->create(['payment_gateway' => PaymentGateway::Stripe]);
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->travel(61)->minutes();

        $this->nelFrattempo(Order::class, $order->id, [
            'status' => OrderStatus::Paid->value,
            'payment_id' => 'pi_arrivato_adesso',
            'paid_at' => now(),
        ]);

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('pi_arrivato_adesso', $order->payment_id);
    }

    /** @return array{Auction, User, User} */
    private function astaConTurnoScaduto(?float $riserva = null, float $secondaOfferta = 150): array
    {
        $primo = User::factory()->create();
        $secondo = User::factory()->create();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200, 'reserve_price' => $riserva]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $primo->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $secondo->id, 'amount' => $secondaOfferta]);

        $auction->forceFill([
            'winner_user_id' => $primo->id,
            'winner_checkout_token' => Str::uuid()->toString(),
            'winner_checkout_deadline' => now()->subMinute(),
            'current_winner_attempt' => 1,
        ])->save();

        return [$auction, $primo, $secondo];
    }

    #[Test]
    public function il_turno_scaduto_non_annulla_l_ordine_pagato_nel_frattempo(): void
    {
        [$auction, $primo] = $this->astaConTurnoScaduto();

        $order = Order::factory()->create(['user_id' => $primo->id, 'payment_gateway' => PaymentGateway::PayPal]);
        $order->forceFill(['auction_id' => $auction->id, 'status' => OrderStatus::Pending])->save();

        $this->nelFrattempo(Order::class, $order->id, [
            'status' => OrderStatus::Paid->value,
            'payment_id' => 'CAPTURE-IN-EXTREMIS',
            'paid_at' => now(),
        ]);

        app(AuctionService::class)->checkWinnerPayments();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame($primo->id, $auction->refresh()->winner_user_id, 'Chi ha pagato resta il vincitore.');
    }

    #[Test]
    public function l_asta_non_passa_a_chi_ha_offerto_sotto_la_riserva(): void
    {
        [$auction] = $this->astaConTurnoScaduto(riserva: 180, secondaOfferta: 150);

        app(AuctionService::class)->checkWinnerPayments();

        $auction->refresh();
        $this->assertNull($auction->winner_user_id, 'Sotto la riserva non si vince: l\'asta resta senza vincitore.');
        $this->assertNull($auction->winner_checkout_token);
    }

    #[Test]
    public function l_asta_passa_al_secondo_se_la_sua_offerta_raggiunge_la_riserva(): void
    {
        [$auction, , $secondo] = $this->astaConTurnoScaduto(riserva: 150, secondaOfferta: 150);

        app(AuctionService::class)->checkWinnerPayments();

        $this->assertSame($secondo->id, $auction->refresh()->winner_user_id);
    }

    #[Test]
    public function la_chiusura_salta_l_asta_allungata_da_un_rilancio_nel_frattempo(): void
    {
        $offerente = User::factory()->create();
        $auction = Auction::factory()->active()->create(['current_bid' => 100]);
        $auction->forceFill(['end_date' => now()->subMinute()])->save();
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $offerente->id, 'amount' => 100]);

        // L'anti-sniping sposta la fine mentre la chiusura sta partendo.
        $this->nelFrattempo(Auction::class, $auction->id, ['end_date' => now()->addMinutes(5)]);

        app(AuctionService::class)->closeEndedAuctions();

        $auction->refresh();
        $this->assertSame(AuctionStatus::Active, $auction->status);
        $this->assertNull($auction->winner_user_id);
    }
}
