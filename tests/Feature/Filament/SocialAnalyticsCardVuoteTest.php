<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\SocialAnalyticsPage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le ripartizioni di Instagram che non hanno niente da mostrare.
 *
 * Due riquadri restavano a schermo con una spiegazione al posto dei dati:
 * "Visualizzazioni: follower e non" perché Meta rifiuta quella combinazione di
 * metrica e ripartizione, "Tap sui link del profilo" perché il dato è davvero
 * zero. Occupavano mezza pagina per raccontare un dettaglio dell'API che a chi
 * guarda le statistiche non serve.
 *
 * Ora spariscono, ma solo finché sono vuote: la voce resta nell'elenco, quindi
 * il riquadro torna da solo il giorno in cui il dato arriva. È la differenza
 * fra nascondere e cancellare, ed è il motivo per cui questo test verifica
 * entrambe le direzioni.
 */
class SocialAnalyticsCardVuoteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function una_ripartizione_che_meta_non_fornisce_non_lascia_il_riquadro_vuoto(): void
    {
        $this->apriLaPaginaCon([
            // null: Meta ha rifiutato la combinazione metrica/ripartizione
            'views_by_follower_type' => null,
            // tutti zero: la chiamata è riuscita, il dato è davvero nessuno
            'profile_links_by_type' => ['website' => 0, 'email' => 0],
            'views_by_media_type' => ['reels' => 264001, 'carousel_container' => 286632],
        ])
            ->assertDontSee('Visualizzazioni: follower e non')
            ->assertDontSee('Tap sui link del profilo')
            ->assertDontSee('Meta non fornisce questa ripartizione')
            ->assertDontSee('Nessun tap nel periodo')
            // quella con i dati resta, altrimenti staremmo solo nascondendo tutto
            ->assertSee('Visualizzazioni per tipo di contenuto');
    }

    #[Test]
    public function il_riquadro_torna_appena_il_dato_arriva(): void
    {
        $this->apriLaPaginaCon([
            'views_by_follower_type' => ['follower' => 180000, 'non_follower' => 95000],
            'profile_links_by_type' => ['website' => 42],
        ])
            ->assertSee('Visualizzazioni: follower e non')
            ->assertSee('Tap sui link del profilo')
            ->assertSee('180.000')
            ->assertSee('42');
    }

    /**
     * Anche con dati presenti, le singole voci a zero non vanno in elenco: una
     * barra lunga zero accanto a un numero zero non dice niente.
     */
    #[Test]
    public function le_voci_a_zero_non_finiscono_in_elenco(): void
    {
        $this->apriLaPaginaCon([
            'profile_links_by_type' => ['website' => 42, 'email' => 0, 'phone' => 0],
        ])
            ->assertSee('Tap sui link del profilo')
            ->assertSee('Sito web')
            ->assertDontSee('Phone');
    }

    /**
     * @param  array<string, array<string, int>|null>  $breakdowns
     */
    private function apriLaPaginaCon(array $breakdowns): Testable
    {
        Http::fake();

        // Senza questi, la pagina si ferma al primo ramo ("App Meta non
        // configurata") e non arriva mai ai riquadri.
        config()->set('services.meta.app_id', '123');
        config()->set('services.meta.app_secret', 'segreto');

        $account = SocialAccount::factory()->create([
            'ig_account_id' => '17841401787337160',
            'ig_username' => 'savinodelbenevolley',
        ]);
        $account->forceFill([
            'access_token' => 'token-di-prova',
            'token_expires_at' => now()->addMonth(),
        ])->save();

        // Lo scheletro del payload si prende dal servizio vero su un account
        // scollegato: ha tutte le chiavi che la view si aspetta, senza doverle
        // elencare a mano qui e senza chiamare Meta.
        $scollegato = SocialAccount::factory()->create();
        $scollegato->forceFill(['access_token' => null])->save();
        $payload = app(SocialAnalyticsService::class)->overview($scollegato, 28);

        $payload['error'] = null;
        $payload['breakdowns'] = $breakdowns;

        $this->mock(
            SocialAnalyticsService::class,
            fn ($mock) => $mock->shouldReceive('overview')->andReturn($payload)
        );

        return Livewire::actingAs($this->gestioneComunicazione())
            ->test(SocialAnalyticsPage::class, ['accountId' => $account->id])
            ->assertSuccessful();
    }

    private function gestioneComunicazione(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::CommunicationManager, 'must_change_password' => false])->save();

        return $user->refresh();
    }
}
