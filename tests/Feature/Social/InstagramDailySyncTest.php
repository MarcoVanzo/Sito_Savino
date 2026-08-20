<?php

namespace Tests\Feature\Social;

use App\Models\SocialAccount;
use App\Models\SocialInsightDaily;
use App\Services\Social\InstagramDailySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La serie giornaliera costa una chiamata per giorno, e le chiamate a Meta sono
 * contate (circa 200 l'ora). Due comportamenti tengono in piedi il modulo:
 * il tetto alle chiamate, che impedisce a un'apertura di pagina di bruciare la
 * quota, e il fatto che un giorno ormai definitivo non si richieda mai più.
 *
 * Se saltasse il secondo, il costo del modulo crescerebbe senza limite pur
 * continuando a mostrare i numeri giusti.
 */
class InstagramDailySyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function scarica_un_giorno_per_chiamata_rispettando_il_tetto(): void
    {
        $account = SocialAccount::factory()->create();
        $this->fakeGraph();

        $result = app(InstagramDailySync::class)->fill($account, days: 5, maxCalls: 2);

        $this->assertSame(2, $result['filled']);
        $this->assertSame(3, $result['pending']);
        $this->assertDatabaseCount('social_insights_daily', 2);

        // Si parte dal più recente: è la parte di grafico che si guarda per prima.
        $days = SocialInsightDaily::query()->orderByDesc('day')->pluck('day')
            ->map(fn ($day): string => $day->format('Y-m-d'))
            ->all();

        $this->assertSame(now()->toDateString(), $days[0]);
        $this->assertSame(now()->subDay()->toDateString(), $days[1]);
    }

    #[Test]
    public function non_richiede_i_giorni_gia_definitivi(): void
    {
        $account = SocialAccount::factory()->create();

        // Tre giorni su cinque già chiusi: restano da chiedere solo gli altri due.
        foreach ([2, 3, 4] as $daysAgo) {
            SocialInsightDaily::query()->create([
                'social_account_id' => $account->id,
                'ig_account_id' => $account->ig_account_id,
                'day' => now()->subDays($daysAgo)->toDateString(),
                'is_final' => true,
            ]);
        }

        $this->fakeGraph();

        $result = app(InstagramDailySync::class)->fill($account, days: 5, maxCalls: 10);

        $this->assertSame(2, $result['filled']);
        $this->assertSame(0, $result['pending']);
    }

    #[Test]
    public function marca_definitivi_solo_i_giorni_che_meta_non_ritocca_piu(): void
    {
        $account = SocialAccount::factory()->create();
        $this->fakeGraph();

        app(InstagramDailySync::class)->fill($account, days: 5, maxCalls: 10);

        // Meta consolida con due giorni di ritardo: oggi e ieri restano aperti.
        $this->assertFalse(SocialInsightDaily::query()->where('day', now()->toDateString())->firstOrFail()->is_final);
        $this->assertTrue(SocialInsightDaily::query()->where('day', now()->subDays(4)->toDateString())->firstOrFail()->is_final);
    }

    #[Test]
    public function si_ferma_senza_perdere_i_giorni_gia_scaricati_se_meta_limita_le_chiamate(): void
    {
        $account = SocialAccount::factory()->create();

        $calls = 0;

        Http::fake(function (Request $request) use (&$calls) {
            if (str_contains($request->url(), 'metric_type=time_series')) {
                return Http::response(['data' => []], 200);
            }

            $calls++;

            // Il terzo giorno Meta chiude: i primi due devono restare in archivio.
            return $calls > 2
                ? Http::response(['error' => ['code' => 4, 'message' => 'Application request limit reached']], 400)
                : Http::response(['data' => [['name' => 'views', 'total_value' => ['value' => 10]]]], 200);
        });

        $result = app(InstagramDailySync::class)->fill($account, days: 5, maxCalls: 10);

        $this->assertSame(2, $result['filled']);
        $this->assertDatabaseCount('social_insights_daily', 2);
    }

    /**
     * `reach` e `follower_count` arrivano in serie storica e coprono tutto il
     * periodo, anche i giorni che il tetto di chiamate non ha toccato. Se quelle
     * righe nascessero già "definitive", il giro dopo verrebbero saltate e i
     * totali di quei giorni (views, interazioni, account raggiunti) resterebbero
     * a zero per sempre.
     */
    #[Test]
    public function i_giorni_visti_solo_dalla_serie_storica_restano_da_scaricare(): void
    {
        $account = SocialAccount::factory()->create();

        $giorni = collect(range(0, 4))->map(fn (int $i): string => now()->subDays($i)->toDateString());

        Http::fake(function (Request $request) use ($giorni) {
            if (str_contains($request->url(), 'metric_type=time_series')) {
                return Http::response(['data' => [[
                    'name' => 'reach',
                    'values' => $giorni->map(fn (string $day): array => [
                        'value' => 100,
                        'end_time' => $day.'T07:00:00+0000',
                    ])->all(),
                ]]], 200);
            }

            return Http::response(['data' => [['name' => 'views', 'total_value' => ['value' => 10]]]], 200);
        });

        $sync = app(InstagramDailySync::class);

        // Prima passata con il tetto di un'apertura di pagina: un giorno solo.
        $sync->fill($account, days: 5, maxCalls: 1);

        $vecchio = SocialInsightDaily::query()->where('day', now()->subDays(4)->toDateString())->firstOrFail();
        $this->assertSame(100, $vecchio->reach);
        $this->assertFalse($vecchio->is_final, 'la serie storica non ha i totali del giorno');

        // Seconda passata, quella del job notturno: deve recuperarli.
        $result = $sync->fill($account, days: 5, maxCalls: 10);

        // Cinque: i tre giorni mai chiesti più i due ancora aperti (oggi e ieri,
        // che Meta può ritoccare) — quello già scaricato al primo giro compreso.
        $this->assertSame(5, $result['filled']);
        $this->assertSame(10, $vecchio->refresh()->views);
        $this->assertTrue($vecchio->is_final);
    }

    #[Test]
    public function un_account_scollegato_non_genera_chiamate(): void
    {
        $account = SocialAccount::factory()->disconnected()->create();

        Http::fake();

        $result = app(InstagramDailySync::class)->fill($account, days: 5);

        $this->assertSame(0, $result['filled']);
        Http::assertNothingSent();
    }

    #[Test]
    public function la_serie_restituita_copre_tutti_i_giorni_chiesti(): void
    {
        $account = SocialAccount::factory()->create();

        SocialInsightDaily::query()->create([
            'social_account_id' => $account->id,
            'ig_account_id' => $account->ig_account_id,
            'day' => now()->subDay()->toDateString(),
            'views' => 42,
            'is_final' => false,
        ]);

        $series = app(InstagramDailySync::class)->series($account, 3);

        // Il grafico non deve avere buchi: i giorni senza dato valgono zero.
        $this->assertCount(3, $series);
        $this->assertSame(0, $series[0]['views']);
        $this->assertSame(42, $series[1]['views']);
        $this->assertSame(now()->toDateString(), $series[2]['day']);
    }

    private function fakeGraph(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'metric_type=time_series')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response([
                'data' => [
                    ['name' => 'views', 'total_value' => ['value' => 10]],
                    ['name' => 'total_interactions', 'total_value' => ['value' => 3]],
                ],
            ], 200);
        });
    }
}
