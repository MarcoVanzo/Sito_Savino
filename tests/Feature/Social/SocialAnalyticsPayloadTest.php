<?php

namespace Tests\Feature\Social;

use App\Models\SocialAccount;
use App\Services\Social\SocialAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La classifica dei contenuti è la parte della pagina su cui si prendono
 * decisioni editoriali: quale post ha reso di più e va ripubblicato.
 *
 * L'ordine sbagliato non si nota — i numeri sono comunque plausibili — ma
 * consiglierebbe il contenuto sbagliato.
 */
class SocialAnalyticsPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function i_post_migliori_sono_ordinati_per_interazioni(): void
    {
        $account = SocialAccount::factory()->create();
        $this->fakeGraph();

        $data = app(SocialAnalyticsService::class)->overview($account, 7);

        $interazioni = array_map(
            static fn (array $post): int => (int) $post['insights']['total_interactions'],
            $data['top_posts'],
        );

        $this->assertSame([208, 188, 157], $interazioni);
    }

    #[Test]
    public function il_tasso_di_un_post_e_sulle_persone_raggiunte(): void
    {
        $account = SocialAccount::factory()->create();
        $this->fakeGraph();

        $migliore = app(SocialAnalyticsService::class)->overview($account, 7)['top_posts'][0];

        // 208 interazioni su 4.800 raggiunti: è la domanda "quanti, fra quelli
        // che l'hanno visto, hanno fatto qualcosa".
        $this->assertSame(4.3, $migliore['rank_rate']);
    }

    #[Test]
    public function un_post_senza_reach_non_produce_un_tasso_inventato(): void
    {
        $account = SocialAccount::factory()->create();
        $this->fakeGraph();

        $senzaReach = collect(app(SocialAnalyticsService::class)->overview($account, 7)['top_posts'])
            ->firstWhere('id', '3');

        $this->assertNotNull($senzaReach);
        $this->assertNull($senzaReach['rank_rate']);
    }

    #[Test]
    public function conta_chi_ha_smesso_di_seguire(): void
    {
        $account = SocialAccount::factory()->create();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'breakdown=follow_type')) {
                // Meta chiama `NON_FOLLOWER` chi ha smesso di seguire. Leggendo
                // `unfollower` il numero restava a zero e nessuno se ne accorgeva:
                // "1.442 nuovi, 0 persi" sembra solo una bella stagione.
                return Http::response(['data' => [[
                    'name' => 'follows_and_unfollows',
                    'total_value' => ['breakdowns' => [['results' => [
                        ['dimension_values' => ['FOLLOWER'], 'value' => 1442],
                        ['dimension_values' => ['NON_FOLLOWER'], 'value' => 1458],
                    ]]]],
                ]]], 200);
            }

            if (str_contains($url, 'metric_type=time_series')) {
                return Http::response(['data' => []], 200);
            }

            if (str_contains($url, '/media')) {
                return Http::response(['data' => []], 200);
            }

            if (str_contains($url, '/insights')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response(['id' => '1', 'followers_count' => 76849], 200);
        });

        $totals = app(SocialAnalyticsService::class)->overview($account, 7)['totals'];

        $this->assertSame(1442, $totals['new_follows']);
        $this->assertSame(1458, $totals['unfollows']);
    }

    #[Test]
    public function i_segnaposto_interni_di_meta_non_finiscono_nelle_ripartizioni(): void
    {
        $account = SocialAccount::factory()->create();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'breakdown=media_product_type')) {
                return Http::response(['data' => [[
                    'name' => 'views',
                    'total_value' => ['breakdowns' => [['results' => [
                        ['dimension_values' => ['REELS'], 'value' => 1293380],
                        // Meta lo restituisce davvero, con conteggi a una cifra:
                        // in pagina compariva come "Default Do Not Use".
                        ['dimension_values' => ['DEFAULT_DO_NOT_USE'], 'value' => 2],
                    ]]]],
                ]]], 200);
            }

            if (str_contains($url, 'metric_type=time_series') || str_contains($url, '/media') || str_contains($url, '/insights')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response(['id' => '1'], 200);
        });

        $breakdowns = app(SocialAnalyticsService::class)->overview($account, 7)['breakdowns'];

        $this->assertSame(['reels' => 1293380], $breakdowns['views_by_media_type']);
    }

    private function fakeGraph(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/media')) {
                return Http::response(['data' => [
                    [
                        'id' => '1', 'caption' => 'Thank you Djeneba', 'media_product_type' => 'FEED',
                        'timestamp' => '2026-08-14T10:00:00+0000', 'permalink' => 'https://instagram.com/p/1',
                        'insights' => ['data' => [
                            ['name' => 'views', 'values' => [['value' => 11700]]],
                            ['name' => 'reach', 'values' => [['value' => 4800]]],
                            ['name' => 'total_interactions', 'values' => [['value' => 208]]],
                        ]],
                    ],
                    [
                        'id' => '2', 'caption' => 'Attenzione', 'media_product_type' => 'FEED',
                        'timestamp' => '2026-08-12T10:00:00+0000',
                        'insights' => ['data' => [
                            ['name' => 'views', 'values' => [['value' => 9500]]],
                            ['name' => 'reach', 'values' => [['value' => 3500]]],
                            ['name' => 'total_interactions', 'values' => [['value' => 188]]],
                        ]],
                    ],
                    [
                        'id' => '3', 'caption' => 'Senza reach', 'media_product_type' => 'FEED',
                        'timestamp' => '2026-08-13T10:00:00+0000',
                        'insights' => ['data' => [
                            ['name' => 'views', 'values' => [['value' => 13500]]],
                            ['name' => 'total_interactions', 'values' => [['value' => 157]]],
                        ]],
                    ],
                ]], 200);
            }

            if (str_contains($url, '/insights')) {
                if (str_contains($url, 'metric_type=time_series')) {
                    return Http::response(['data' => []], 200);
                }

                return Http::response(['data' => [
                    ['name' => 'views', 'total_value' => ['value' => 100]],
                    ['name' => 'total_interactions', 'total_value' => ['value' => 10]],
                ]], 200);
            }

            // Profilo Instagram o campi della Pagina Facebook.
            return Http::response([
                'id' => '17841400000000000',
                'username' => 'savinodelbenevolley',
                'name' => 'Savino Del Bene Volley',
                'followers_count' => 76834,
                'media_count' => 2800,
            ], 200);
        });
    }
}
