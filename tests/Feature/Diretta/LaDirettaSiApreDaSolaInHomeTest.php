<?php

namespace Tests\Feature\Diretta;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Services\Diretta\LimitiGeograficiDellaDiretta;
use App\Support\MatchDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «Apri la diretta da sola» (Sponsor → Diretta): a gara in corso la home apre
 * la finestra della diretta, ma solo se YouTube conferma che la Lega non l'ha
 * bloccata in qualche paese.
 */
class LaDirettaSiApreDaSolaInHomeTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    private static string $privateKey;

    /** @var array<string, list<string>> I limiti che YouTube dichiara in questo momento. */
    private array $limiti = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $exported);
        self::$privateKey = $exported;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-04 21:00', 'Europe/Rome'));
        config()->set('services.ga4.service_account_json', json_encode([
            'client_email' => 'analytics@savino.iam.gserviceaccount.com',
            'private_key' => self::$privateKey,
            'project_id' => 'savino-analytics',
        ]));
        SiteSetting::set('diretta_apertura_automatica', true, 'diretta');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function la_home_non_aspetta_youtube_e_la_visita_dopo_apre_la_diretta(): void
    {
        $this->youtubeRisponde([]);
        $this->garaAlle('20:30');

        // La prima visita non sa ancora niente: risponde subito e chiede a
        // YouTube dopo la risposta.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('matchDay.apriDiretta', false)
            ->where('matchDay.popupDiretta', true)
        );

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://www.googleapis.com/youtube/v3/videos')
            && $request['id'] === 'dQw4w9WgXcQ');
        $this->assertTrue(MatchDay::stato()['apri_diretta']);
    }

    #[Test]
    public function un_esito_vecchio_vale_finche_youtube_non_ne_da_uno_nuovo(): void
    {
        $this->withoutDefer();
        $this->youtubeRisponde([]);
        $this->garaAlle('20:30');
        $this->youtubeGiaInterrogato();

        // La Lega blocca la diretta a gara in corso: la home lo sa alla
        // richiesta successiva a quella che ha chiesto di nuovo.
        $this->limiti = ['blocked' => ['IT']];
        $this->travel(11)->minutes();

        $this->assertTrue(MatchDay::stato()['apri_diretta']);
        $this->assertFalse(MatchDay::stato()['apri_diretta']);
    }

    /** @return array<string, array{array<string, list<string>>}> */
    public static function limitiPerPaese(): array
    {
        return [
            'bloccata in Italia' => [['blocked' => ['IT']]],
            'visibile solo in alcuni paesi' => [['allowed' => ['CH', 'DE']]],
        ];
    }

    /** @param  array<string, list<string>>  $limiti */
    #[Test]
    #[DataProvider('limitiPerPaese')]
    public function una_diretta_bloccata_in_qualche_paese_non_si_apre_da_sola(array $limiti): void
    {
        $this->garaAlle('20:30');
        $this->youtubeRisponde($limiti);
        $this->youtubeGiaInterrogato();

        $stato = MatchDay::stato();

        // Né il pop-up: chi sta in quei paesi troverebbe «video non
        // disponibile». I pulsanti di hero e fascia restano.
        $this->assertFalse($stato['apri_diretta']);
        $this->assertFalse($stato['popup_diretta']);
        $this->assertTrue($stato['diretta_vicina']);
    }

    #[Test]
    public function se_youtube_non_risponde_o_manca_il_service_account_non_si_apre(): void
    {
        $this->garaAlle('20:30');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-di-prova']),
            'www.googleapis.com/youtube/*' => Http::response(['error' => 'quotaExceeded'], 403),
        ]);
        $this->youtubeGiaInterrogato();
        $stato = MatchDay::stato();
        $this->assertFalse($stato['apri_diretta']);
        // Non sapere non è un blocco: il pop-up resta com'era.
        $this->assertTrue($stato['popup_diretta']);

        config()->set('services.ga4.service_account_json', '');
        $this->youtubeGiaInterrogato();
        $this->assertFalse(MatchDay::stato()['apri_diretta']);
    }

    #[Test]
    public function prima_del_fischio_d_inizio_o_a_interruttore_spento_youtube_non_si_interroga(): void
    {
        $this->youtubeRisponde([]);
        // Il pop-up della diretta chiederebbe a YouTube per conto suo.
        SiteSetting::set('match_day_popup_attivo', false, 'home');
        $gara = $this->garaAlle('21:30');

        $this->assertFalse(MatchDay::stato()['apri_diretta']);

        $gara->update(['match_date' => now()->setTime(20, 30)]);
        SiteSetting::set('diretta_apertura_automatica', false, 'diretta');
        $this->assertFalse(MatchDay::stato()['apri_diretta']);

        $gara->update(['status' => GameStatus::Completed]);
        SiteSetting::set('diretta_apertura_automatica', true, 'diretta');
        $this->assertFalse(MatchDay::stato()['apri_diretta']);

        Http::assertNothingSent();
    }

    #[Test]
    public function i_link_che_non_sono_di_youtube_non_si_aprono_da_soli(): void
    {
        $this->youtubeRisponde([]);
        $this->garaAlle('20:30', 'https://vimeo.com/123456789');

        $stato = MatchDay::stato();

        $this->assertFalse($stato['apri_diretta']);
        $this->assertTrue($stato['popup_diretta']);
        Http::assertNothingSent();
    }

    private function garaAlle(string $ora, string $link = self::LINK): Game
    {
        $savino = Team::factory()->internal()->create(['name' => 'Savino Del Bene']);
        $avversaria = Team::factory()->create(['name' => 'Avversaria']);

        return Game::factory()->create([
            'home_team_id' => $avversaria->id,
            'away_team_id' => $savino->id,
            'match_date' => now()->setTimeFromTimeString($ora),
            'status' => GameStatus::Scheduled,
            'competition_type' => 'Campionato',
            'stream_url' => $link,
        ]);
    }

    /** @param  array<string, list<string>>  $limiti */
    private function youtubeRisponde(array $limiti): void
    {
        $this->limiti = $limiti;

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-di-prova']),
            'www.googleapis.com/youtube/*' => fn () => Http::response(['items' => [[
                'id' => 'dQw4w9WgXcQ',
                'contentDetails' => $this->limiti === [] ? [] : ['regionRestriction' => $this->limiti],
            ]]]),
        ]);
    }

    /** Come dopo una visita: l'esito di YouTube è già salvato. */
    private function youtubeGiaInterrogato(): void
    {
        app(LimitiGeograficiDellaDiretta::class)->verifica('dQw4w9WgXcQ');
    }
}
