<?php

namespace Tests\Feature\Console;

use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I due comandi girano di notte, senza nessuno che guardi: devono uscire con
 * successo quando non c'è niente da fare (altrimenti il fallimento diventa
 * rumore quotidiano da ignorare) e non devono fermarsi al primo account rotto.
 */
class AnalyticsSyncCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_sincronizzazione_meta_senza_account_collegati_non_e_un_errore(): void
    {
        Http::fake();

        $this->artisan('social:sync-meta')
            ->expectsOutputToContain('Nessun account Meta collegato')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function la_sincronizzazione_meta_riempie_la_serie_degli_account_collegati(): void
    {
        $account = SocialAccount::factory()->create(['name' => 'Prima squadra']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'metric_type=time_series')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response(['data' => [['name' => 'views', 'total_value' => ['value' => 7]]]], 200);
        });

        $this->artisan('social:sync-meta --days=3')->assertSuccessful();

        $this->assertDatabaseCount('social_insights_daily', 3);
        $this->assertNotNull($account->fresh()->last_synced_at);
    }

    #[Test]
    public function un_account_scollegato_viene_saltato(): void
    {
        SocialAccount::factory()->disconnected()->create();

        Http::fake();

        $this->artisan('social:sync-meta')->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function la_sincronizzazione_ga4_senza_service_account_avvisa_e_non_fallisce(): void
    {
        config()->set('services.ga4.service_account_json', null);
        config()->set('services.ga4.service_account_file', null);

        Http::fake();

        $this->artisan('analytics:sync-ga4')
            ->expectsOutputToContain('non configurato')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
