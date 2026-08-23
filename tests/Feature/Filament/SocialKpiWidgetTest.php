<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\Analytics\SocialKpiWidget;
use App\Models\SocialAccount;
use App\Services\Social\SocialAnalyticsService;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gli otto numeri in testa alla pagina Social Analytics.
 *
 * La distinzione che conta è fra zero e "non disponibile": Meta non fornisce
 * reach e nuovi follower a tutti gli account, e mostrare uno zero al posto di
 * un dato mancante racconta una cosa falsa — sembra che la settimana sia
 * andata male, non che il dato non ci sia.
 */
class SocialKpiWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function widget(?int $accountId): SocialKpiWidget
    {
        $widget = new SocialKpiWidget;
        $widget->accountId = $accountId;
        $widget->days = 28;

        return $widget;
    }

    /**
     * @return array<int, Stat>
     */
    private function schede(SocialKpiWidget $widget): array
    {
        $metodo = new \ReflectionMethod($widget, 'getStats');
        $metodo->setAccessible(true);

        return $metodo->invoke($widget);
    }

    private function accountConInstagram(): SocialAccount
    {
        return SocialAccount::factory()->create([
            'ig_account_id' => '17841400000000000',
            'ig_username' => 'savinodelbenevolley',
        ]);
    }

    private function panoramica(array $totali, array $profilo = [], array $giornaliero = []): void
    {
        $servizio = Mockery::mock(SocialAnalyticsService::class);
        $servizio->shouldReceive('overview')->andReturn([
            'totals' => $totali,
            'profile' => $profilo,
            'daily' => $giornaliero,
        ]);

        $this->app->instance(SocialAnalyticsService::class, $servizio);
    }

    private function totaliCompleti(array $sovrascritture = []): array
    {
        return array_merge([
            'follower_delta' => 120,
            'views' => 45000,
            'reach' => 30000,
            'accounts_engaged' => 5000,
            'total_interactions' => 7500,
            'new_follows' => 300,
            'unfollows' => 180,
            'profile_links_taps' => 90,
        ], $sovrascritture);
    }

    #[Test]
    public function senza_account_selezionato_non_ci_sono_schede(): void
    {
        $this->assertSame([], $this->schede($this->widget(null)));
    }

    #[Test]
    public function un_account_senza_instagram_non_ha_numeri_da_mostrare(): void
    {
        $account = SocialAccount::factory()->create(['ig_account_id' => null]);

        $this->assertSame([], $this->schede($this->widget($account->id)));
    }

    #[Test]
    public function senza_dati_dal_servizio_non_si_mostra_una_griglia_di_zeri(): void
    {
        $this->panoramica([]);

        $this->assertSame([], $this->schede($this->widget($this->accountConInstagram()->id)));
    }

    #[Test]
    public function con_i_dati_completi_le_schede_sono_otto(): void
    {
        $this->panoramica($this->totaliCompleti(), ['followers_count' => 25000, 'media_count' => 1400]);

        $schede = $this->schede($this->widget($this->accountConInstagram()->id));

        $this->assertCount(8, $schede);
        $this->assertSame('25.000', $schede[0]->getValue());
        $this->assertSame('45.000', $schede[1]->getValue());
        $this->assertSame('1.400', $schede[7]->getValue());
    }

    /**
     * Reach e nuovi follower mancanti si scrivono "n/d": uno zero direbbe che
     * nessuno ha visto i post.
     */
    #[Test]
    public function le_metriche_che_meta_non_fornisce_si_dicono_non_disponibili(): void
    {
        $this->panoramica($this->totaliCompleti([
            'reach' => null,
            'new_follows' => null,
            'unfollows' => null,
        ]), ['followers_count' => 25000]);

        $schede = $this->schede($this->widget($this->accountConInstagram()->id));

        $this->assertSame('n/d', $schede[2]->getValue(), 'Account raggiunti');
        $this->assertSame('n/d', $schede[5]->getValue(), 'Nuovi follower');
        $this->assertSame('gray', $schede[5]->getColor(), 'Un dato mancante non è né buono né cattivo.');
    }

    #[Test]
    public function un_calo_di_follower_si_vede_dal_colore_e_dal_segno(): void
    {
        $this->panoramica($this->totaliCompleti(['follower_delta' => -45]), ['followers_count' => 25000]);

        $follower = $this->schede($this->widget($this->accountConInstagram()->id))[0];

        $this->assertSame('danger', $follower->getColor());
        $this->assertStringContainsString('−45', $follower->getDescription());
        $this->assertSame('heroicon-m-arrow-trending-down', $follower->getDescriptionIcon());
    }

    #[Test]
    public function una_crescita_di_follower_si_vede_col_piu(): void
    {
        $this->panoramica($this->totaliCompleti(['follower_delta' => 1200]), ['followers_count' => 25000]);

        $follower = $this->schede($this->widget($this->accountConInstagram()->id))[0];

        $this->assertSame('success', $follower->getColor());
        $this->assertStringContainsString('+1.200', $follower->getDescription());
    }

    /**
     * Con una serie troppo corta il confronto col periodo precedente non si
     * può fare: si dice, invece di mostrare una freccia in su per finta.
     */
    #[Test]
    public function senza_confronto_possibile_la_variazione_si_dichiara_non_disponibile(): void
    {
        $this->panoramica($this->totaliCompleti(['follower_delta' => null]), ['followers_count' => 25000]);

        $follower = $this->schede($this->widget($this->accountConInstagram()->id))[0];

        $this->assertSame('Variazione non disponibile', $follower->getDescription());
        $this->assertSame('gray', $follower->getColor());
    }

    #[Test]
    public function le_percentuali_si_calcolano_sul_reach_e_sui_follower(): void
    {
        $this->panoramica($this->totaliCompleti([
            'reach' => 10000,
            'total_interactions' => 2500,
            'accounts_engaged' => 500,
        ]), ['followers_count' => 20000]);

        $schede = $this->schede($this->widget($this->accountConInstagram()->id));

        $this->assertStringContainsString('25,0%', $schede[3]->getDescription());
        $this->assertStringContainsString('2,5%', $schede[4]->getDescription());
    }

    #[Test]
    public function i_grafici_seguono_la_serie_giornaliera(): void
    {
        $this->panoramica(
            $this->totaliCompleti(),
            ['followers_count' => 25000],
            [
                ['follower_count' => 100, 'views' => 10, 'reach' => 5, 'total_interactions' => 2],
                ['follower_count' => 110, 'views' => 20, 'reach' => 8, 'total_interactions' => 3],
            ]
        );

        $schede = $this->schede($this->widget($this->accountConInstagram()->id));

        $this->assertSame([100.0, 110.0], $schede[0]->getChart());
        $this->assertSame([10.0, 20.0], $schede[1]->getChart());
    }
}
