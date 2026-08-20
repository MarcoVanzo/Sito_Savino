<?php

namespace Tests\Feature\Social;

use App\Models\SocialAccount;
use App\Models\SocialInsightDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * In produzione quindici giorni erano marcati definitivi pur avendo solo reach e
 * follower: le righe nate dalla serie storica prima che i totali fossero
 * scaricati. Restando definitive, non sarebbero mai più state richieste.
 */
class SbloccoGiorniSenzaTotaliTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sblocca_i_giorni_con_la_sola_serie_storica(): void
    {
        $account = SocialAccount::factory()->create();

        $daSbloccare = SocialInsightDaily::query()->create([
            'social_account_id' => $account->id,
            'ig_account_id' => $account->ig_account_id,
            'day' => '2026-07-21',
            'reach' => 34292,
            'follower_count' => 77,
            'is_final' => true,
        ]);

        $completo = SocialInsightDaily::query()->create([
            'social_account_id' => $account->id,
            'ig_account_id' => $account->ig_account_id,
            'day' => '2026-07-22',
            'reach' => 54720,
            'views' => 96242,
            'total_interactions' => 2837,
            'accounts_engaged' => 2204,
            'is_final' => true,
        ]);

        // Un giorno davvero senza attività: nessun dato da recuperare.
        $vuoto = SocialInsightDaily::query()->create([
            'social_account_id' => $account->id,
            'ig_account_id' => $account->ig_account_id,
            'day' => '2026-07-23',
            'is_final' => true,
        ]);

        (require database_path('migrations/2026_08_20_150000_sblocca_i_giorni_instagram_senza_totali.php'))->up();

        $this->assertFalse($daSbloccare->refresh()->is_final, 'il giorno senza totali va richiesto di nuovo');
        $this->assertTrue($completo->refresh()->is_final, 'un giorno completo resta definitivo');
        $this->assertTrue($vuoto->refresh()->is_final, 'un giorno davvero a zero non si richiede');
    }
}
