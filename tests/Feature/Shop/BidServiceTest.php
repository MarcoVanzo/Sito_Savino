<?php

namespace Tests\Feature\Shop;

use App\Enums\AuctionStatus;
use App\Mail\AuctionOutbid;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le regole con cui si accetta un'offerta.
 *
 * Sono tre: l'asta dev'essere aperta, l'importo deve stare fra il rilancio
 * minimo e il salto massimo, e chi è già in testa non può rilanciare su sé
 * stesso. Il tetto al salto esiste perché un'offerta enormemente sopra la
 * precedente chiude di fatto l'asta, per errore di battitura o per dispetto.
 */
class BidServiceTest extends TestCase
{
    use RefreshDatabase;

    private BidService $servizio;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->servizio = app(BidService::class);
    }

    private function astaAperta(array $attributi = []): Auction
    {
        $asta = Auction::factory()->create(array_merge([
            'starting_price' => 100.0,
            'bid_increment' => 5.0,
            'max_bid_jump' => 100.0,
            'current_bid' => null,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(3),
        ], $attributi));

        $asta->forceFill(['status' => AuctionStatus::Active])->save();

        return $asta->refresh();
    }

    #[Test]
    public function la_prima_offerta_parte_dal_prezzo_di_partenza_piu_il_rilancio(): void
    {
        $asta = $this->astaAperta();
        $utente = User::factory()->create();

        $offerta = $this->servizio->placeBid($asta, $utente, 105.0);

        $this->assertEquals(105.0, (float) $offerta->amount);
        $this->assertTrue((bool) $offerta->is_valid);
        $this->assertEquals(105.0, (float) $asta->refresh()->current_bid);
    }

    #[Test]
    public function sotto_il_rilancio_minimo_l_offerta_e_rifiutata(): void
    {
        $asta = $this->astaAperta();
        $utente = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'offerta minima è di €105,00.");

        $this->servizio->placeBid($asta, $utente, 104.99);
    }

    #[Test]
    public function sopra_il_salto_massimo_l_offerta_e_rifiutata(): void
    {
        $asta = $this->astaAperta();
        $utente = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'offerta massima consentita è di €200,00.");

        $this->servizio->placeBid($asta, $utente, 200.01);
    }

    #[Test]
    public function il_rilancio_successivo_si_misura_sull_offerta_in_testa_non_sul_prezzo_di_partenza(): void
    {
        $asta = $this->astaAperta();
        $primo = User::factory()->create();
        $secondo = User::factory()->create();

        $this->servizio->placeBid($asta, $primo, 150.0);
        $offerta = $this->servizio->placeBid($asta->refresh(), $secondo, 155.0);

        $this->assertEquals(155.0, (float) $offerta->amount);
    }

    #[Test]
    public function chi_e_gia_in_testa_non_puo_superare_se_stesso(): void
    {
        $asta = $this->astaAperta();
        $utente = User::factory()->create();
        $this->servizio->placeBid($asta, $utente, 110.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sei già il miglior offerente.');

        $this->servizio->placeBid($asta->refresh(), $utente, 120.0);
    }

    #[Test]
    public function su_un_asta_non_ancora_partita_non_si_offre(): void
    {
        $asta = $this->astaAperta();
        $asta->forceFill(['status' => AuctionStatus::Scheduled])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'asta non è attiva.");

        $this->servizio->placeBid($asta->refresh(), User::factory()->create(), 110.0);
    }

    #[Test]
    public function su_un_asta_scaduta_non_si_offre(): void
    {
        $asta = $this->astaAperta();
        $asta->forceFill(['end_date' => now()->subMinute()])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'asta è già terminata.");

        $this->servizio->placeBid($asta->refresh(), User::factory()->create(), 110.0);
    }

    /**
     * Anti-sniping: un'offerta all'ultimo minuto sposta avanti la chiusura,
     * così chi era in testa ha il tempo di rispondere.
     */
    #[Test]
    public function un_offerta_in_dirittura_d_arrivo_prolunga_l_asta(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'anti_snipe_minutes'],
            ['value' => '5', 'group' => 'auctions', 'type' => 'text']
        );

        $asta = $this->astaAperta(['end_date' => now()->addMinutes(2)]);
        $scadenzaPrima = $asta->end_date;

        $this->servizio->placeBid($asta, User::factory()->create(), 110.0);

        $this->assertTrue(
            $asta->refresh()->end_date->greaterThan($scadenzaPrima),
            'La chiusura doveva spostarsi in avanti.'
        );
    }

    #[Test]
    public function un_offerta_lontana_dalla_chiusura_non_prolunga_niente(): void
    {
        $asta = $this->astaAperta(['end_date' => now()->addDays(2)]);
        $scadenzaPrima = $asta->end_date;

        $this->servizio->placeBid($asta, User::factory()->create(), 110.0);

        $this->assertEquals(
            $scadenzaPrima->timestamp,
            $asta->refresh()->end_date->timestamp,
            'La chiusura non doveva muoversi.'
        );
    }

    #[Test]
    public function chi_viene_superato_riceve_una_mail(): void
    {
        $asta = $this->astaAperta();
        $primo = User::factory()->create();
        $secondo = User::factory()->create();

        $this->servizio->placeBid($asta, $primo, 110.0);
        $this->servizio->placeBid($asta->refresh(), $secondo, 120.0);

        Mail::assertQueued(AuctionOutbid::class, fn ($mail) => $mail->hasTo($primo->email));
    }

    #[Test]
    public function alla_prima_offerta_non_c_e_nessuno_da_avvisare(): void
    {
        $asta = $this->astaAperta();

        $this->servizio->placeBid($asta, User::factory()->create(), 110.0);

        Mail::assertNothingQueued();
    }

    /**
     * Senza rilancio dichiarato sull'asta vale quello delle impostazioni.
     *
     * Il campo ha il cast `decimal:2`, quindi uno zero arriva come "0.00":
     * una stringa che PHP considera vera. Con il vecchio `?:` il ripiego non
     * scattava e l'asta si bloccava, perché soglia minima e massima
     * coincidevano entrambe col prezzo base.
     */
    #[Test]
    public function il_rilancio_minimo_ripiega_sulle_impostazioni(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'min_bid_increment'],
            ['value' => '20', 'group' => 'auctions', 'type' => 'text']
        );

        $asta = $this->astaAperta(['bid_increment' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'offerta minima è di €120,00.");

        $this->servizio->placeBid($asta, User::factory()->create(), 119.0);
    }

    #[Test]
    public function anche_il_salto_massimo_ripiega_sulle_impostazioni(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'max_bid_jump'],
            ['value' => '30', 'group' => 'auctions', 'type' => 'text']
        );

        $asta = $this->astaAperta(['max_bid_jump' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'offerta massima consentita è di €130,00.");

        $this->servizio->placeBid($asta, User::factory()->create(), 131.0);
    }

    /**
     * Con entrambe le soglie a zero l'asta si bloccava: minimo e massimo
     * valevano il prezzo base, e nessun rilancio passava piu'.
     */
    #[Test]
    public function un_asta_con_le_soglie_a_zero_accetta_comunque_rilanci(): void
    {
        $asta = $this->astaAperta(['bid_increment' => 0, 'max_bid_jump' => 0]);

        $offerta = $this->servizio->placeBid($asta, User::factory()->create(), 150.0);

        $this->assertEquals(150.0, (float) $offerta->amount);
    }

    #[Test]
    public function l_offerta_viene_registrata_e_diventa_quella_in_testa(): void
    {
        $asta = $this->astaAperta();
        $utente = User::factory()->create();

        $this->servizio->placeBid($asta, $utente, 130.0);

        $this->assertDatabaseHas('bids', [
            'auction_id' => $asta->id,
            'user_id' => $utente->id,
            'is_valid' => true,
        ]);
        $this->assertEquals(130.0, (float) $asta->refresh()->highestBid()->amount);
        $this->assertEquals(1, Bid::where('auction_id', $asta->id)->count());
    }
}
