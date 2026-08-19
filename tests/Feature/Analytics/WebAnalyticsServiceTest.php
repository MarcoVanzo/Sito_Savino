<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsSite;
use App\Models\WebAnalyticsDaily;
use App\Services\Analytics\Ga4ReportAssembler;
use App\Services\Analytics\WebAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il servizio ha due compiti che nessun altro può coprire: tenere in piedi la
 * pagina quando Google non risponde, e conservare la serie giornaliera perché
 * lo storico non dipenda da una property che può essere chiusa o sostituita.
 *
 * Sono anche i due comportamenti che, se si rompono, non si notano: la pagina
 * continua a disegnarsi.
 */
class WebAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private static string $privateKey;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Una chiave vera serve perché la firma del JWT è parte del percorso
        // sotto test: con una chiave finta si fermerebbe prima di chiamare Google.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $exported);
        self::$privateKey = $exported;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ga4.service_account_json', json_encode([
            'client_email' => 'analytics@savino.iam.gserviceaccount.com',
            'private_key' => self::$privateKey,
        ]));

        Cache::flush();
    }

    #[Test]
    public function salva_in_archivio_i_giorni_riportati_da_google(): void
    {
        $site = AnalyticsSite::factory()->create(['property_id' => '123456789']);
        $today = Ga4ReportAssembler::today();
        $ieri = $today->modify('-1 day');

        $this->fakeGoogle([
            1 => [
                ['dimensionValues' => [['value' => $ieri->format('Ymd')]], 'metricValues' => [
                    ['value' => '12'], ['value' => '9'], ['value' => '15'], ['value' => '40'], ['value' => '8'], ['value' => '600'],
                ]],
            ],
        ]);

        app(WebAnalyticsService::class)->overview($site, 7);

        // Solo il giorno con traffico: i giorni riempiti a zero dall'assemblatore
        // non devono sovrascrivere quello che c'è già in archivio.
        $this->assertDatabaseCount('web_analytics_daily', 1);

        $row = WebAnalyticsDaily::query()->firstOrFail();
        $this->assertSame($site->id, $row->analytics_site_id);
        $this->assertSame($ieri->format('Y-m-d'), $row->day->format('Y-m-d'));
        $this->assertSame(12, $row->active_users);
        // Meno di due giorni fa: GA4 può ancora rielaborarlo.
        $this->assertFalse($row->is_final);
    }

    #[Test]
    public function marca_definitivi_i_giorni_che_google_non_rielabora_piu(): void
    {
        $site = AnalyticsSite::factory()->create(['property_id' => '123456789']);
        $vecchio = Ga4ReportAssembler::today()->modify('-5 days');

        $this->fakeGoogle([
            1 => [
                ['dimensionValues' => [['value' => $vecchio->format('Ymd')]], 'metricValues' => [
                    ['value' => '3'], ['value' => '1'], ['value' => '4'], ['value' => '9'], ['value' => '2'], ['value' => '120'],
                ]],
            ],
        ]);

        app(WebAnalyticsService::class)->overview($site, 28);

        $this->assertTrue(WebAnalyticsDaily::query()->firstOrFail()->is_final);
    }

    #[Test]
    public function quando_google_non_risponde_ripiega_sulla_serie_gia_salvata(): void
    {
        $site = AnalyticsSite::factory()->create(['property_id' => '123456789']);
        $ieri = Ga4ReportAssembler::today()->modify('-1 day')->format('Y-m-d');

        WebAnalyticsDaily::query()->create([
            'analytics_site_id' => $site->id,
            'property_id' => $site->property_id,
            'day' => $ieri,
            'active_users' => 7,
            'new_users' => 3,
            'sessions' => 9,
            'page_views' => 21,
            'engaged_sessions' => 5,
            'engagement_seconds' => 450,
            'is_final' => true,
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-di-prova'], 200),
            'analyticsdata.googleapis.com/*' => Http::response('', 503),
        ]);

        $data = app(WebAnalyticsService::class)->overview($site, 7);

        // La pagina resta in piedi, dice che i dati sono vecchi e mostra i numeri
        // che conosce: senza questo, un disservizio di Google la lascerebbe bianca.
        $this->assertNotNull($data['degraded']);
        $this->assertSame('unavailable', $data['degraded']['reason']);
        $this->assertSame(7, $data['totals']['active_users']);
        $this->assertSame(21, $data['totals']['page_views']);
    }

    #[Test]
    public function senza_archivio_un_guasto_diventa_un_errore_dichiarato(): void
    {
        $site = AnalyticsSite::factory()->create(['property_id' => '123456789']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-di-prova'], 200),
            'analyticsdata.googleapis.com/*' => Http::response([
                'error' => ['message' => 'User does not have sufficient permissions for this property.'],
            ], 403),
        ]);

        $data = app(WebAnalyticsService::class)->overview($site, 7);

        $this->assertFalse($data['ok']);
        $this->assertSame('not_authorized', $data['error']['reason']);
        // Il messaggio deve dire dove si risolve, non ripetere il codice HTTP.
        $this->assertStringContainsString('Visualizzatore', $data['error']['message']);
    }

    #[Test]
    public function senza_service_account_non_prova_nemmeno_a_chiamare_google(): void
    {
        config()->set('services.ga4.service_account_json', null);
        config()->set('services.ga4.service_account_file', null);

        $site = AnalyticsSite::factory()->create(['property_id' => '123456789']);

        Http::fake();

        $data = app(WebAnalyticsService::class)->overview($site, 7);

        $this->assertSame('not_configured', $data['error']['reason']);
        Http::assertNothingSent();
    }

    #[Test]
    public function non_chiama_google_due_volte_per_lo_stesso_periodo(): void
    {
        $site = AnalyticsSite::factory()->create(['property_id' => '123456789']);
        $this->fakeGoogle();

        $service = app(WebAnalyticsService::class);
        $service->overview($site, 7);
        $service->overview($site, 7);

        // Due batch (i report sono otto, il limite di Google è cinque) più il
        // tempo reale e il token: la seconda lettura non deve aggiungere nulla.
        Http::assertSentCount(4);
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $rowsByIndex  righe per indice di report
     */
    private function fakeGoogle(array $rowsByIndex = []): void
    {
        $reports = [];

        for ($i = 0; $i < 8; $i++) {
            $reports[] = ['rows' => $rowsByIndex[$i] ?? []];
        }

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-di-prova', 'expires_in' => 3600], 200),
            '*:batchRunReports' => Http::sequence()
                ->push(['reports' => array_slice($reports, 0, 5)])
                ->push(['reports' => array_slice($reports, 5)]),
            '*:runRealtimeReport' => Http::response([
                'rows' => [['dimensionValues' => [], 'metricValues' => [['value' => '2']]]],
            ], 200),
        ]);
    }
}
