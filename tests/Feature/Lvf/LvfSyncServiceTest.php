<?php

namespace Tests\Feature\Lvf;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use App\Models\TeamLvfClubId;
use App\Services\Lvf\LvfSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LvfSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const OWN_CLUB = 710955;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lvf.club_ids', [self::OWN_CLUB]);
        config()->set('services.lvf.base_url', 'https://www.legavolleyfemminile.it');
    }

    /**
     * @param  array{home?: int, away?: int}  $sets
     */
    private function matchHtml(int $matchId, array $sets = [], string $date = '04/10/2026', int $homeClub = self::OWN_CLUB, int $awayClub = 710949): string
    {
        $scores = $sets === []
            ? ''
            : sprintf('<th class="num">%d</th>', $sets['home']);
        $awayScores = $sets === []
            ? ''
            : sprintf('<th class="num">%d</th>', $sets['away']);

        return <<<HTML
            <table class="table risultati">
              <thead><tr>
                <th>#3001</th><th>{$date}</th><th>17:00</th>
                <th>LVF A1 Fineco <br> 1<sup>a</sup> Giornata - Andata</th>
                <th>Pala BigMat - Firenze</th>
                <th><a href="https://www.legavolleyfemminile.it/match-center/{$matchId}/">MATCH CENTER</a></th>
              </tr></thead>
              <tbody>
                <tr>{$scores}<td><a href="/club/club/{$homeClub}/">Savino Del Bene Scandicci</a></td></tr>
                <tr>{$awayScores}<td><a href="/club/club/{$awayClub}/">Il Bisonte Firenze</a></td></tr>
              </tbody>
            </table>
            HTML;
    }

    /**
     * @param  list<array{pos: int, club: int, name: string, points: int}>  $rows
     */
    private function standingsHtml(array $rows): string
    {
        $body = '';

        foreach ($rows as $row) {
            $body .= <<<HTML
                <tr>
                  <th class="num">{$row['pos']}</th>
                  <td><a href="/club/club/{$row['club']}/">{$row['name']}</a></td>
                  <td>{$row['points']}</td><td>10</td><td>8</td><td>2</td>
                  <td>5</td><td>2</td><td>1</td><td>1</td><td>1</td><td>0</td>
                  <td>26</td><td>12</td><td>900</td><td>800</td><td>2.16</td><td>1.12</td>
                </tr>
                HTML;
        }

        return <<<HTML
            <table class="table classifica">
              <thead><tr>
                <th>Pos</th><th>Squadra</th><th>PT</th><th>G</th><th>V</th><th>P</th>
                <th>3-0</th><th>3-1</th><th>3-2</th><th>2-3</th><th>1-3</th><th>0-3</th>
                <th>SV</th><th>SP</th><th>PF</th><th>PS</th><th>QS</th><th>QP</th>
              </tr></thead>
              <tbody>{$body}</tbody>
            </table>
            HTML;
    }

    private function fakeSite(string $calendar, ?string $results = null, ?string $standings = null): void
    {
        // Http::fake() accoda gli stub invece di sostituirli e il primo che
        // corrisponde vince: senza una factory pulita, i test che simulano due
        // sincronizzazioni successive riceverebbero due volte la prima risposta.
        Http::swap(new Factory);

        Http::fake([
            '*legavolleyfemminile.it/calendario*' => Http::response($calendar),
            '*legavolleyfemminile.it/risultati*' => Http::response($results ?? $calendar),
            '*legavolleyfemminile.it/classifica*' => Http::response(
                $standings ?? $this->standingsHtml([
                    ['pos' => 1, 'club' => self::OWN_CLUB, 'name' => 'Savino Del Bene Scandicci', 'points' => 30],
                    ['pos' => 2, 'club' => 710949, 'name' => 'Il Bisonte Firenze', 'points' => 24],
                ])
            ),
        ]);
    }

    #[Test]
    public function importa_gare_squadre_e_classifica(): void
    {
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));

        $stats = LvfSyncService::make()->sync(2026);

        $this->assertSame(1, $stats['matches_created']);
        $this->assertSame(2, $stats['standings']);

        $game = Game::firstWhere('lvf_match_id', 747982);
        $this->assertNotNull($game);
        $this->assertSame(3, $game->home_score);
        $this->assertSame(1, $game->away_score);
        $this->assertSame(GameStatus::Completed, $game->status);
        $this->assertSame(1, $game->matchday);
        $this->assertSame('Andata', $game->phase);
        $this->assertSame('Pala BigMat - Firenze', $game->location);

        $this->assertDatabaseCount('standings', 2);
        $this->assertSame(30, Standing::orderBy('position')->first()->points);
    }

    #[Test]
    public function rilanciare_la_sincronizzazione_non_duplica_nulla(): void
    {
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));

        LvfSyncService::make()->sync(2026);
        $stats = LvfSyncService::make()->sync(2026);

        $this->assertSame(0, $stats['matches_created']);
        $this->assertSame(1, $stats['matches_updated']);
        $this->assertSame(0, $stats['teams_created']);

        $this->assertDatabaseCount('games', 1);
        $this->assertDatabaseCount('standings', 2);
        $this->assertSame(2, Team::count());
    }

    #[Test]
    public function aggancia_la_squadra_del_club_invece_di_duplicarla(): void
    {
        // La squadra esiste già in archivio con rose e statistiche collegate:
        // un doppione ne spezzerebbe il legame.
        $own = Team::create([
            'name' => 'Savino Del Bene Volley',
            'slug' => 'savino-del-bene-volley',
            'is_internal' => true,
        ]);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));

        LvfSyncService::make()->sync(2026);

        $this->assertSame(2, Team::count(), 'La squadra del club è stata duplicata.');
        $this->assertSame(self::OWN_CLUB, $own->fresh()->lvf_club_id);
        $this->assertSame('Savino Del Bene Volley', $own->fresh()->name);
        $this->assertSame($own->id, Game::first()->home_team_id);
    }

    #[Test]
    public function non_tocca_le_gare_inserite_a_mano(): void
    {
        $own = Team::create(['name' => 'Savino', 'slug' => 'savino', 'is_internal' => true]);
        $rival = Team::create(['name' => 'Amichevole FC', 'slug' => 'amichevole-fc', 'is_internal' => false]);
        $season = Season::create(['name' => '2026/2027', 'is_current' => true, 'lvf_season_year' => 2026]);

        $manual = Game::create([
            'season_id' => $season->id,
            'home_team_id' => $own->id,
            'away_team_id' => $rival->id,
            'match_date' => now()->addWeek(),
            'status' => GameStatus::Scheduled,
            'location' => 'Palestra comunale',
        ]);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));

        LvfSyncService::make()->sync(2026);

        $manual->refresh();
        $this->assertNull($manual->lvf_match_id);
        $this->assertSame('Palestra comunale', $manual->location);
        $this->assertSame(GameStatus::Scheduled, $manual->status);
        $this->assertDatabaseCount('games', 2);
    }

    #[Test]
    public function una_gara_non_disputata_resta_senza_punteggio(): void
    {
        // Il calendario non espone set; la pagina risultati emette 0-0.
        $this->fakeSite(
            calendar: $this->matchHtml(747982),
            results: $this->matchHtml(747982, ['home' => 0, 'away' => 0]),
        );

        LvfSyncService::make()->sync(2026);

        $game = Game::firstWhere('lvf_match_id', 747982);
        $this->assertNull($game->home_score);
        $this->assertNull($game->away_score);
        $this->assertSame(GameStatus::Scheduled, $game->status);
    }

    #[Test]
    public function il_punteggio_arriva_dai_risultati_e_i_metadati_dal_calendario(): void
    {
        // Solo il calendario espone giornata e fase; solo i risultati i set.
        $resultsOnly = <<<'HTML'
            <table class="table risultati">
              <thead><tr>
                <th>#3001</th><th>04/10/2026</th><th>17:00</th><th>Pala BigMat - Firenze</th>
                <th><a href="https://www.legavolleyfemminile.it/match-center/747982/">MATCH CENTER</a></th>
              </tr></thead>
              <tbody>
                <tr><th class="num">3</th><td><a href="/club/club/710955/">Savino Del Bene Scandicci</a></td></tr>
                <tr><th class="num">2</th><td><a href="/club/club/710949/">Il Bisonte Firenze</a></td></tr>
              </tbody>
            </table>
            HTML;

        $this->fakeSite(calendar: $this->matchHtml(747982), results: $resultsOnly);

        LvfSyncService::make()->sync(2026);

        $game = Game::firstWhere('lvf_match_id', 747982);
        $this->assertSame(3, $game->home_score);
        $this->assertSame(2, $game->away_score);
        $this->assertSame(1, $game->matchday, 'La giornata del calendario è andata persa.');
        $this->assertSame('Andata', $game->phase);
    }

    #[Test]
    public function un_risultato_aggiornato_sovrascrive_quello_precedente(): void
    {
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 0]));
        LvfSyncService::make()->sync(2026);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 2]));
        LvfSyncService::make()->sync(2026);

        $game = Game::firstWhere('lvf_match_id', 747982);
        $this->assertSame(2, $game->away_score);
        $this->assertDatabaseCount('games', 1);
    }

    #[Test]
    public function una_squadra_uscita_dal_campionato_sparisce_dalla_classifica(): void
    {
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));
        LvfSyncService::make()->sync(2026);
        $this->assertDatabaseCount('standings', 2);

        $this->fakeSite(
            calendar: $this->matchHtml(747982, ['home' => 3, 'away' => 1]),
            standings: $this->standingsHtml([
                ['pos' => 1, 'club' => self::OWN_CLUB, 'name' => 'Savino Del Bene Scandicci', 'points' => 33],
            ]),
        );
        LvfSyncService::make()->sync(2026);

        $this->assertDatabaseCount('standings', 1);
        $this->assertSame(33, Standing::first()->points);
    }

    #[Test]
    public function una_classifica_vuota_non_cancella_quella_esistente(): void
    {
        // Una pagina in manutenzione non deve svuotare la classifica del sito.
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));
        LvfSyncService::make()->sync(2026);
        $this->assertDatabaseCount('standings', 2);

        $this->fakeSite(
            calendar: $this->matchHtml(747982, ['home' => 3, 'away' => 1]),
            standings: '<html><body><p>Manutenzione</p></body></html>',
        );
        $stats = LvfSyncService::make()->sync(2026);

        $this->assertSame(0, $stats['standings']);
        $this->assertDatabaseCount('standings', 2);
    }

    #[Test]
    public function non_aggancia_una_squadra_interna_gia_collegata_a_un_altro_club(): void
    {
        // Se la prima squadra interna è già legata a un altro club della Lega,
        // ripuntarla al nostro ne falserebbe gare e classifica.
        $b1 = Team::create([
            'name' => 'Serie B1',
            'slug' => 'serie-b1',
            'is_internal' => true,
            'lvf_club_id' => 999999,
        ]);

        TeamLvfClubId::create(['team_id' => $b1->id, 'lvf_club_id' => 999999]);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));
        LvfSyncService::make()->sync(2026);

        $this->assertSame(999999, Team::firstWhere('slug', 'serie-b1')->lvf_club_id);
        $this->assertNotSame(
            'Serie B1',
            Team::firstWhere('lvf_club_id', self::OWN_CLUB)->name,
            'Il club è stato agganciato alla squadra sbagliata.'
        );
    }

    #[Test]
    public function la_stessa_societa_non_viene_duplicata_fra_stagioni_diverse(): void
    {
        // La Lega rinumera gli identificativi di club a ogni stagione: senza
        // alias, importare una seconda stagione creerebbe un doppione di ogni
        // squadra e spezzerebbe classifica, gare e statistiche storiche.
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1], awayClub: 710949));
        LvfSyncService::make()->sync(2026);

        $this->assertSame(2, Team::count());

        // Stessa denominazione, identificativi diversi: è la stagione precedente.
        $this->fakeSite(
            calendar: $this->matchHtml(747000, ['home' => 3, 'away' => 0], '10/10/2025', 710918, 710913),
            standings: $this->standingsHtml([
                ['pos' => 1, 'club' => 710918, 'name' => 'Savino Del Bene Scandicci', 'points' => 40],
                ['pos' => 2, 'club' => 710913, 'name' => 'Il Bisonte Firenze', 'points' => 20],
            ]),
        );
        config()->set('services.lvf.club_ids', [self::OWN_CLUB, 710918]);
        LvfSyncService::make()->sync(2025);

        $this->assertSame(2, Team::count(), 'Le squadre sono state duplicate cambiando stagione.');

        $savino = Team::firstWhere('slug', 'savino-del-bene-scandicci');
        $this->assertNotNull($savino);
        $this->assertEqualsCanonicalizing(
            [self::OWN_CLUB, 710918],
            $savino->lvfClubIds()->pluck('lvf_club_id')->all()
        );
    }

    #[Test]
    public function la_squadra_della_societa_resta_una_sola_su_piu_stagioni(): void
    {
        // La squadra interna ha un alias per ogni stagione importata: al secondo
        // giro va arricchita con l'identificativo nuovo, non scartata perché
        // "già agganciata". Sbagliare qui la duplica e le sue gare spariscono
        // dalle pagine pubbliche, che filtrano su `is_internal`.
        $own = Team::create(['name' => 'Savino Del Bene Volley', 'slug' => 'sdb', 'is_internal' => true]);

        config()->set('services.lvf.club_ids', [710918, self::OWN_CLUB]);

        $this->fakeSite($this->matchHtml(747000, ['home' => 3, 'away' => 0], '10/10/2025', 710918, 710913));
        LvfSyncService::make()->sync(2025);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1], '04/10/2026', self::OWN_CLUB, 710949));
        LvfSyncService::make()->sync(2026);

        $this->assertSame(
            1,
            Team::where('is_internal', true)->count(),
            'La squadra della società è stata duplicata cambiando stagione.'
        );

        $this->assertEqualsCanonicalizing(
            [710918, self::OWN_CLUB],
            $own->fresh()->lvfClubIds()->pluck('lvf_club_id')->all()
        );

        // Entrambe le gare devono risultare della nostra squadra.
        $this->assertSame(2, Game::where('home_team_id', $own->id)->count());
    }

    #[Test]
    public function il_logo_caricato_dal_cms_non_viene_sovrascritto_dallimport(): void
    {
        Storage::fake('public');

        // Un PNG vero: la media library genera le conversioni e rifiuterebbe
        // una stringa qualsiasi.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $own = Team::create(['name' => 'Savino Del Bene Volley', 'slug' => 'sdb', 'is_internal' => true]);
        $own->addMediaFromString($png)
            ->usingFileName('logo-custom.png')
            ->toMediaCollection(Team::LOGO_CUSTOM);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));
        Http::fake(['*imgSocietaSquadra*' => Http::response($png)]);

        LvfSyncService::make()->sync(2026);

        $own->refresh();

        $this->assertTrue($own->hasCustomLogo());
        $this->assertSame(
            'logo-custom.png',
            $own->getFirstMedia(Team::LOGO_CUSTOM)->file_name,
            'La sincronizzazione ha sovrascritto il logo caricato dal CMS.'
        );
        // Il logo mostrato resta quello scelto in redazione.
        $this->assertStringContainsString('logo-custom', (string) $own->logoUrl());
    }

    #[Test]
    public function riusa_la_stagione_gia_presente_invece_di_crearne_una_nuova(): void
    {
        $existing = Season::create(['name' => '2026/2027', 'is_current' => true]);

        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));
        LvfSyncService::make()->sync(2026);

        $this->assertSame(1, Season::count());
        $this->assertSame(2026, $existing->fresh()->lvf_season_year);
    }

    #[Test]
    public function se_il_sito_non_risponde_non_viene_importato_nulla(): void
    {
        Http::swap(new Factory);
        Http::fake(['*legavolleyfemminile.it/*' => Http::response('', 503)]);

        $this->expectException(\RuntimeException::class);

        try {
            LvfSyncService::make()->sync(2026);
        } finally {
            $this->assertDatabaseCount('games', 0);
            $this->assertDatabaseCount('standings', 0);
        }
    }

    #[Test]
    public function se_cade_solo_la_classifica_le_gare_importate_restano_e_la_vecchia_classifica_e_intatta(): void
    {
        // La sincronizzazione non è atomica fra gare e classifica: ogni gara ha
        // la sua transazione. Se la classifica non arriva, le gare già scritte
        // restano — sono idempotenti e il sync successivo completa il lavoro —
        // e soprattutto la classifica precedente non va persa.
        $this->fakeSite($this->matchHtml(747982, ['home' => 3, 'away' => 1]));
        LvfSyncService::make()->sync(2026);
        $this->assertDatabaseCount('standings', 2);

        Http::swap(new Factory);
        Http::fake([
            '*legavolleyfemminile.it/calendario*' => Http::response($this->matchHtml(747983, ['home' => 3, 'away' => 0], '11/10/2026')),
            '*legavolleyfemminile.it/risultati*' => Http::response($this->matchHtml(747983, ['home' => 3, 'away' => 0], '11/10/2026')),
            '*legavolleyfemminile.it/classifica*' => Http::response('', 503),
        ]);

        try {
            LvfSyncService::make()->sync(2026);
            $this->fail('La classifica ha risposto 503: era attesa una RuntimeException.');
        } catch (\RuntimeException) {
            // atteso
        }

        // Le gare importate prima dell'errore restano, e la classifica
        // precedente non viene svuotata.
        $this->assertDatabaseCount('games', 2);
        $this->assertDatabaseCount('standings', 2);
    }
}
