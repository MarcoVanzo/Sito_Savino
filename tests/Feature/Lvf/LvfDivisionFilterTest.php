<?php

namespace Tests\Feature\Lvf;

use App\Models\Game;
use App\Services\Lvf\LvfSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le pagine pubbliche della Lega elencano Serie A1 e Serie A2 nello stesso
 * calendario. Senza filtro il sito pubblicava 454 gare e 31 squadre sotto il
 * titolo "Campionato Serie A1", con giornate fino alla 17ª, mentre la
 * classifica restava di 14 righe.
 */
class LvfDivisionFilterTest extends TestCase
{
    use RefreshDatabase;

    private const OWN_CLUB = 710955;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lvf.club_ids', [self::OWN_CLUB]);
        config()->set('services.lvf.base_url', 'https://www.legavolleyfemminile.it');
        config()->set('services.lvf.divisions', ['a1']);
        config()->set('services.lvf.excluded_divisions', ['a2']);
    }

    private function matchHtml(int $matchId, string $competition, int $homeClub, int $awayClub, string $homeName, string $awayName): string
    {
        return <<<HTML
            <table class="table risultati">
              <thead><tr>
                <th>#{$matchId}</th><th>04/10/2026</th><th>17:00</th>
                <th>{$competition} <br> 1<sup>a</sup> Giornata - Andata</th>
                <th>Pala BigMat - Firenze</th>
                <th><a href="https://www.legavolleyfemminile.it/match-center/{$matchId}/">MATCH CENTER</a></th>
              </tr></thead>
              <tbody>
                <tr><td><a href="/club/club/{$homeClub}/">{$homeName}</a></td></tr>
                <tr><td><a href="/club/club/{$awayClub}/">{$awayName}</a></td></tr>
              </tbody>
            </table>
            HTML;
    }

    private function fakeSite(string $calendar): void
    {
        Http::swap(new Factory);

        Http::fake([
            '*legavolleyfemminile.it/calendario*' => Http::response($calendar),
            '*legavolleyfemminile.it/risultati*' => Http::response($calendar),
            '*legavolleyfemminile.it/classifica*' => Http::response('<table class="table classifica"><tbody></tbody></table>'),
        ]);
    }

    #[Test]
    public function it_imports_only_the_tracked_division(): void
    {
        $this->fakeSite(
            $this->matchHtml(3001, 'LVF A1 Fineco', self::OWN_CLUB, 710949, 'Savino Del Bene Scandicci', 'Il Bisonte Firenze')
            .$this->matchHtml(4001, 'LVF A2 Tigotà', 720001, 720002, 'Volley Marsala', 'Cremonese')
        );

        LvfSyncService::make()->sync(2026);

        $this->assertDatabaseHas('games', ['lvf_match_id' => 3001]);
        $this->assertDatabaseMissing('games', ['lvf_match_id' => 4001]);
        $this->assertSame(1, Game::whereNotNull('lvf_match_id')->count());
    }

    #[Test]
    public function it_removes_matches_of_other_divisions_imported_before_the_filter_existed(): void
    {
        // Primo giro senza filtro: entra anche la A2.
        config()->set('services.lvf.excluded_divisions', []);

        $this->fakeSite(
            $this->matchHtml(3001, 'LVF A1 Fineco', self::OWN_CLUB, 710949, 'Savino Del Bene Scandicci', 'Il Bisonte Firenze')
            .$this->matchHtml(4001, 'LVF A2 Tigotà', 720001, 720002, 'Volley Marsala', 'Cremonese')
        );

        LvfSyncService::make()->sync(2026);
        $this->assertDatabaseHas('games', ['lvf_match_id' => 4001]);

        // Secondo giro col filtro attivo: la potatura toglie la gara di A2.
        config()->set('services.lvf.excluded_divisions', ['a2']);

        $this->fakeSite(
            $this->matchHtml(3001, 'LVF A1 Fineco', self::OWN_CLUB, 710949, 'Savino Del Bene Scandicci', 'Il Bisonte Firenze')
            .$this->matchHtml(4001, 'LVF A2 Tigotà', 720001, 720002, 'Volley Marsala', 'Cremonese')
        );

        LvfSyncService::make()->sync(2026);

        $this->assertDatabaseHas('games', ['lvf_match_id' => 3001]);
        $this->assertDatabaseMissing('games', ['lvf_match_id' => 4001]);
    }

    #[Test]
    public function it_keeps_matches_whose_competition_is_not_recognised(): void
    {
        // Coppa Italia e Champions vivono sulle stesse pagine: un'etichetta
        // sconosciuta non deve mai far sparire una gara valida.
        $this->fakeSite(
            $this->matchHtml(5001, 'Coppa Italia Frecciarossa', self::OWN_CLUB, 710949, 'Savino Del Bene Scandicci', 'Il Bisonte Firenze')
        );

        LvfSyncService::make()->sync(2026);

        $this->assertDatabaseHas('games', ['lvf_match_id' => 5001]);
    }
}
