<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pagina mette insieme due fonti con affidabilità diversa: gli iscritti sono
 * nostri, i risultati delle campagne li conosce solo ActiveCampaign. Il punto
 * fermo è che la seconda non possa portarsi via la prima.
 */
class NewsletterAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.activecampaign.url', 'https://savino.api-us1.com');
        config()->set('services.activecampaign.key', 'chiave-di-prova');

        Cache::flush();
    }

    #[Test]
    public function conta_gli_iscritti_e_le_nuove_iscrizioni_del_periodo(): void
    {
        NewsletterSubscriber::factory()->count(3)->create(['created_at' => now()->subDays(2)]);
        NewsletterSubscriber::factory()->create(['created_at' => now()->subDays(40)]);
        NewsletterSubscriber::factory()->create([
            'created_at' => now()->subDays(3),
            'unsubscribed_at' => now(),
        ]);

        Http::fake(['*' => Http::response(['campaigns' => []], 200)]);

        $data = app(NewsletterAnalyticsService::class)->overview(28);

        $this->assertSame(5, $data['subscribers']['total']);
        $this->assertSame(4, $data['subscribers']['active']);
        $this->assertSame(1, $data['subscribers']['unsubscribed']);
        // Quello di quaranta giorni fa è fuori dal periodo.
        $this->assertSame(4, $data['subscribers']['new_in_period']);
    }

    #[Test]
    public function la_serie_delle_iscrizioni_copre_tutti_i_giorni(): void
    {
        NewsletterSubscriber::factory()->count(2)->create(['created_at' => now()->subDay()]);

        Http::fake(['*' => Http::response(['campaigns' => []], 200)]);

        $data = app(NewsletterAnalyticsService::class)->overview(7);

        $this->assertCount(7, $data['daily']);
        $this->assertSame(2, $data['daily'][5]['subscriptions']);
        $this->assertSame(0, $data['daily'][0]['subscriptions']);
    }

    #[Test]
    public function le_medie_sono_pesate_sugli_invii(): void
    {
        Http::fake(['*' => Http::response(['campaigns' => [
            // Una campagna piccola con percentuali altissime non deve trascinare
            // la media di una da migliaia di destinatari.
            ['id' => '1', 'name' => 'Test interno', 'status' => 5, 'send_amt' => 10, 'uniqueopens' => 10, 'uniquelinkclicks' => 10],
            ['id' => '2', 'name' => 'Newsletter di ottobre', 'status' => 5, 'send_amt' => 990, 'uniqueopens' => 99, 'uniquelinkclicks' => 0],
        ]], 200)]);

        $data = app(NewsletterAnalyticsService::class)->overview(28);

        $this->assertSame(1000, $data['averages']['sent']);
        // 109 aperture su 1000 invii, non la media fra 100% e 10%.
        $this->assertSame(10.9, $data['averages']['open_rate']);
        $this->assertSame(1.0, $data['averages']['click_rate']);
    }

    #[Test]
    public function calcola_i_tassi_di_ogni_campagna_e_somma_i_rimbalzi(): void
    {
        Http::fake(['*' => Http::response(['campaigns' => [
            [
                'id' => '7', 'name' => 'Presentazione squadra', 'status' => 5, 'sdate' => '2026-08-01 10:00:00',
                'send_amt' => 200, 'opens' => 300, 'uniqueopens' => 80, 'linkclicks' => 40,
                'uniquelinkclicks' => 20, 'unsubscribes' => 3, 'hardbounces' => 2, 'softbounces' => 5,
            ],
        ]], 200)]);

        $campagna = app(NewsletterAnalyticsService::class)->overview(28)['campaigns'][0];

        $this->assertSame(40.0, $campagna['open_rate']);
        $this->assertSame(10.0, $campagna['click_rate']);
        $this->assertSame(7, $campagna['bounces']);
    }

    #[Test]
    public function scarta_le_bozze_e_le_campagne_programmate(): void
    {
        Http::fake(['*' => Http::response(['campaigns' => [
            ['id' => '1', 'name' => 'Bozza', 'status' => 0, 'send_amt' => 0],
            ['id' => '2', 'name' => 'Inviata', 'status' => 5, 'send_amt' => 100],
        ]], 200)]);

        $campagne = app(NewsletterAnalyticsService::class)->overview(28)['campaigns'];

        $this->assertCount(1, $campagne);
        $this->assertSame('Inviata', $campagne[0]['name']);
    }

    #[Test]
    public function se_activecampaign_non_risponde_gli_iscritti_restano(): void
    {
        NewsletterSubscriber::factory()->count(2)->create();

        Http::fake(['*' => Http::response('', 500)]);

        $data = app(NewsletterAnalyticsService::class)->overview(28);

        $this->assertFalse($data['campaigns_ok']);
        $this->assertNotNull($data['campaigns_message']);
        // La metà che conosciamo per certo non deve sparire con la telefonata persa.
        $this->assertSame(2, $data['subscribers']['total']);
    }

    #[Test]
    public function senza_configurazione_non_chiama_activecampaign(): void
    {
        config()->set('services.activecampaign.url', null);
        config()->set('services.activecampaign.key', null);

        Http::fake();

        $data = app(NewsletterAnalyticsService::class)->overview(28);

        $this->assertFalse($data['campaigns_ok']);
        Http::assertNothingSent();
    }
}
