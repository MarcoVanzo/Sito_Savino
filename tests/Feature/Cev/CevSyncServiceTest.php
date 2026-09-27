<?php

namespace Tests\Feature\Cev;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use App\Services\Cev\CevException;
use App\Services\Cev\CevSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il giro completo di `cev:sync` sul portale simulato con le pagine vere:
 * 2025/26 (competizione 1802, giocata per intero) e 2026/27 (1948).
 */
class CevSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://www-old.cev.eu/Competition-Area';

    private Team $savino;

    /** @var array<string, string> */
    private array $sostituzioni = [];

    /**
     * Sostituzioni per una sola pagina, per simularne una rotta.
     *
     * @var array<string, array<string, string>>
     */
    private array $sostituzioniPer = [];

    /**
     * Trasformazioni libere di una pagina, quando una sostituzione fissa non basta.
     *
     * @var array<string, \Closure(string): string>
     */
    private array $trasformazioniPer = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cev.base_url', self::BASE);
        config()->set('services.cev.pausa_ms', 0);
        config()->set('services.cev.nomi_della_societa', ['Savino Del Bene SCANDICCI']);

        Season::create(['name' => '2025/2026', 'lvf_season_year' => 2025, 'is_current' => false]);
        Season::create(['name' => '2026/2027', 'lvf_season_year' => 2026, 'is_current' => true]);

        // Le migrazioni creano già le squadre del vivaio: la prima squadra la
        // si aggiunge dopo, così il test prova che la sincronizzazione non
        // aggancia la Champions alla prima squadra interna che trova.
        Team::firstOrCreate(['slug' => 'serie-b1'], ['name' => 'Serie B1 / U19', 'is_internal' => true, 'category' => 'B1']);
        $this->savino = Team::create(['name' => 'Savino Del Bene Volley', 'slug' => 'savino-del-bene-volley', 'is_internal' => true, 'category' => 'A1']);

        Http::fake(fn (Request $request) => Http::response($this->pagina($request->url())));
    }

    /**
     * Risponde con la fixture che corrisponde all'indirizzo chiesto.
     */
    private function pagina(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $id = (int) ($q['ID'] ?? 0);
        $pid = (int) ($q['PID'] ?? 0);

        $nome = match (true) {
            str_contains($url, '/Competition.aspx') => "competizione-{$id}",
            str_contains($url, '/CompetitionStandings.aspx') => "classifica-{$id}-gironi",
            str_contains($url, '/CompetitionView.aspx') => 'partite-'.$id.'-'.[
                3340 => 'turni-preliminari', 3341 => 'gironi', 3342 => 'play-off', 3343 => 'quarti', 3344 => 'final-four',
                3534 => 'turni-preliminari', 3535 => 'gironi', 3537 => 'play-off', 3538 => 'quarti', 3539 => 'final-four',
            ][$pid],
        };

        $html = strtr((string) file_get_contents(base_path("tests/Fixtures/Cev/{$nome}.html")), $this->sostituzioni);

        $html = strtr($html, $this->sostituzioniPer[$nome] ?? []);

        return isset($this->trasformazioniPer[$nome]) ? ($this->trasformazioniPer[$nome])($html) : $html;
    }

    private function sync(int $competizione = 1802, int $stagione = 2025): array
    {
        return CevSyncService::make()->sync($competizione, $stagione);
    }

    #[Test]
    public function importa_la_competizione_e_aggancia_la_nostra_squadra_a_quella_di_a1(): void
    {
        $stats = $this->sync();

        // 16 gare nei turni preliminari, di cui 2 ancora con un segnaposto;
        // 60 nei gironi, 6 nei play off, 8 nei quarti, 4 in Final Four.
        $this->assertSame(92, $stats['matches_created']);
        $this->assertSame(2, $stats['matches_skipped']);
        $this->assertSame(92, Game::where('competition_type', CompetitionType::ChampionsLeague)->count());

        // Nessun doppione della società: le gare del Savino sono della squadra di A1.
        $this->assertSame(1, Team::whereRaw('LOWER(name) LIKE ?', ['%savino%'])->count());
        $nostre = Game::where('home_team_id', $this->savino->id)->orWhere('away_team_id', $this->savino->id)->get();
        $this->assertCount(12, $nostre);
        $this->assertTrue($nostre->every(fn (Game $g) => $g->status === GameStatus::Completed));
    }

    #[Test]
    public function rilanciarlo_non_duplica_niente(): void
    {
        $this->sync();
        $squadre = Team::count();

        $stats = $this->sync();

        $this->assertSame(0, $stats['matches_created']);
        $this->assertSame(92, $stats['matches_updated']);
        $this->assertSame(0, $stats['teams_created']);
        $this->assertSame($squadre, Team::count());
        $this->assertSame(92, Game::whereNotNull('cev_match_id')->count());
    }

    #[Test]
    public function importa_la_classifica_del_nostro_girone(): void
    {
        $stats = $this->sync();

        $this->assertSame('Pool A', $stats['girone']);

        $righe = Standing::with('team')->where('competition_type', CompetitionType::ChampionsLeague)->orderBy('position')->get();
        $this->assertSame(['Pool A'], $righe->pluck('girone')->unique()->values()->all());
        $this->assertSame(['VakifBank ISTANBUL', 'Savino Del Bene Volley', 'CS Volei Alba BLAJ', 'Volero LE CANNET'], $righe->pluck('team.name')->all());

        $nostra = $righe->firstWhere('team_id', $this->savino->id);
        $this->assertSame([2, 13, 15, 7], [$nostra->position, $nostra->points, $nostra->sets_won, $nostra->sets_lost]);
    }

    #[Test]
    public function il_lavoro_della_redazione_non_si_tocca(): void
    {
        $season = Season::where('lvf_season_year', 2025)->first();
        $avversaria = Team::create(['name' => 'Amichevole', 'slug' => 'amichevole', 'is_internal' => false]);

        $manuale = Game::create([
            'season_id' => $season->id,
            'home_team_id' => $this->savino->id,
            'away_team_id' => $avversaria->id,
            'match_date' => '2025-12-20 18:00:00',
            'status' => GameStatus::Scheduled,
            'competition_type' => CompetitionType::ChampionsLeague,
        ]);

        $this->sync();

        $importata = Game::whereNotNull('cev_match_id')->where('home_team_id', $this->savino->id)->first();
        $importata->update(['stream_url' => 'https://www.youtube.com/watch?v=abc123']);

        $this->sync();

        $this->assertModelExists($manuale);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $importata->fresh()->stream_url);
    }

    #[Test]
    public function una_gara_che_sparisce_dal_portale_si_toglie_se_non_e_giocata(): void
    {
        $this->sync(1948, 2026);

        $gara = Game::where('home_team_id', $this->savino->id)->orWhere('away_team_id', $this->savino->id)->orderBy('match_date')->first();
        $this->assertNotNull($gara);

        // Il portale la ritira (rinvio, errore di calendario): stessa riga,
        // identificativo diverso.
        $this->sostituzioni = ["mID={$gara->cev_match_id}&" => 'mID=999999999&'];

        $stats = $this->sync(1948, 2026);

        $this->assertModelMissing($gara);
        $this->assertSame(1, $stats['matches_removed']);
        $this->assertSame(1, $stats['matches_created']);
    }

    #[Test]
    public function senza_la_stagione_in_archivio_non_crea_niente(): void
    {
        $this->expectException(CevException::class);

        $this->sync(1948, 2030);
    }

    #[Test]
    public function il_comando_riporta_l_esito(): void
    {
        config()->set('services.cev.competition_id', 1948);
        config()->set('services.cev.season_year', 2026);

        $this->artisan('cev:sync')->expectsOutputToContain('Pool D')->assertSuccessful();

        $this->assertSame('Girone D', __('enums.game.phase.pool_d'));
    }

    #[Test]
    public function rifiuta_l_edizione_della_stagione_sbagliata(): void
    {
        // 1802 è la Champions 2025/26 ("Volley 2026"): dentro la stagione
        // 2026/27 sarebbe un calendario vecchio spacciato per quello di oggi.
        $this->expectException(CevException::class);
        $this->expectExceptionMessage('edizione 2026');

        $this->sync(1802, 2026);
    }

    #[Test]
    public function rifiuta_la_champions_maschile(): void
    {
        $this->sostituzioniPer['competizione-1948'] = ['Volley 2027 | Women' => 'Volley 2027 | Men'];

        $this->expectException(CevException::class);
        $this->expectExceptionMessage('non è la Champions League femminile');

        $this->sync(1948, 2026);
    }

    #[Test]
    public function una_fase_illeggibile_non_fa_cancellare_le_sue_gare(): void
    {
        $this->sync(1948, 2026);
        $gareDeiGironi = Game::where('phase', 'like', 'Pool %')->count();
        $this->assertGreaterThan(0, $gareDeiGironi);

        // La pagina dei gironi torna senza gare (errore del portale).
        $this->sostituzioniPer['partite-1948-gironi'] = ['_div_match' => '_div_altro'];

        $stats = $this->sync(1948, 2026);

        $this->assertSame(0, $stats['matches_removed']);
        $this->assertSame($gareDeiGironi, Game::where('phase', 'like', 'Pool %')->count());
    }

    #[Test]
    public function senza_nessuna_gara_leggibile_e_un_errore_non_un_giro_vuoto(): void
    {
        $this->sostituzioni = ['_div_match' => '_div_altro'];

        $this->expectException(CevException::class);
        $this->expectExceptionMessage('il portale ha cambiato le pagine');

        $this->sync(1948, 2026);
    }

    #[Test]
    public function le_italiane_riusano_la_squadra_della_lega(): void
    {
        $conegliano = Team::create(['name' => 'Prosecco Doc A.Carraro Imoco Conegliano', 'slug' => 'conegliano', 'is_internal' => false, 'lvf_club_id' => 710953]);
        $novara = Team::create(['name' => 'Igor Gorgonzola Novara', 'slug' => 'novara', 'is_internal' => false, 'lvf_club_id' => 710948]);

        $this->sync();

        // I confronti di MySQL non distinguono le maiuscole: si contano le
        // squadre della città, che devono restare una.
        $this->assertSame(1, Team::where('name', 'like', '%conegliano%')->count(), 'doppione Conegliano');
        $this->assertSame(1, Team::where('name', 'like', '%novara%')->count(), 'doppione Novara');
        $this->assertGreaterThan(0, Game::where('home_team_id', $conegliano->id)->orWhere('away_team_id', $conegliano->id)->count());
        $this->assertGreaterThan(0, Game::where('home_team_id', $novara->id)->orWhere('away_team_id', $novara->id)->count());

        // Le squadre straniere della stessa città restano distinte.
        $this->assertSame(1, Team::where('name', 'VakifBank ISTANBUL')->count(), 'VakifBank');
        $this->assertSame(1, Team::where('name', 'Eczacibasi Dynavit ISTANBUL')->count(), 'Eczacibasi');
    }

    #[Test]
    public function se_la_cev_rinomina_la_societa_lo_dice(): void
    {
        config()->set('services.cev.nomi_della_societa', ['Savino Del Bene FIRENZE']);

        $stats = $this->sync();

        $this->assertFalse($stats['societa_trovata']);
    }

    #[Test]
    public function la_nostra_gara_con_l_avversaria_da_decidere_compare_comunque(): void
    {
        $this->sync(1948, 2026);

        // CLVW 49: Savino - "Winner Matches 3rd Round CLVW 11/12", 25/11 alle 18:30.
        $gara = Game::with('awayTeam')->where('home_team_id', $this->savino->id)
            ->where('match_date', '2026-11-25 18:30:00')->first();

        $this->assertNotNull($gara);
        $this->assertSame(Team::SLUG_DA_DEFINIRE, $gara->awayTeam->slug);
        $this->assertSame('Avversaria da definire', $gara->awayTeam->nomePubblico());
        $this->app->setLocale('en');
        $this->assertSame('Opponent to be confirmed', $gara->awayTeam->nomePubblico());

        // Le gare fra due segnaposto o di altre squadre restano fuori.
        $this->assertSame(1, Team::where('slug', Team::SLUG_DA_DEFINIRE)->count());
        $this->assertSame(0, Game::whereNotIn('home_team_id', [$this->savino->id])
            ->whereNotIn('away_team_id', [$this->savino->id])
            ->where(fn ($q) => $q->whereHas('homeTeam', fn ($t) => $t->where('slug', Team::SLUG_DA_DEFINIRE))
                ->orWhereHas('awayTeam', fn ($t) => $t->where('slug', Team::SLUG_DA_DEFINIRE)))
            ->count());

        // Il 12/11 la CEV pubblica la vincente: stessa gara, avversaria vera.
        $this->trasformazioniPer['partite-1948-gironi'] = function (string $html): string {
            $inizio = strpos($html, '>CLVW 49<');
            $pezzo = substr($html, $inizio, 6000);
            $nuovo = preg_replace(
                ['/(_Image3" src="[^"]*\?ID=)0"/', '/Winner Matches 3rd Round CLVW 11\/12/'],
                ['${1}14999"', 'Mladost ZAGREB'],
                $pezzo,
                1,
            );

            return substr_replace($html, (string) $nuovo, $inizio, strlen($pezzo));
        };

        $this->sync(1948, 2026);

        $gara->refresh()->load('awayTeam');
        $this->assertSame('Mladost ZAGREB', $gara->awayTeam->name);
        $this->assertSame(1, Game::where('match_date', '2026-11-25 18:30:00')->where('home_team_id', $this->savino->id)->count());
    }

    #[Test]
    public function la_pagina_dei_risultati_mostra_l_avversaria_da_definire_tradotta(): void
    {
        $this->sync(1948, 2026);
        Season::where('lvf_season_year', 2026)->update(['is_current' => true]);

        $this->get(route('stagione.cev'))->assertInertia(fn ($page) => $page
            ->where('games', fn ($games) => collect($games)->contains(fn ($g) => $g['away'] === 'Avversaria da definire' && $g['homeIsOwn']))
        );
    }
}
