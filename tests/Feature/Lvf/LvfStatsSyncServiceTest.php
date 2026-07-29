<?php

namespace Tests\Feature\Lvf;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamLvfClubId;
use App\Services\Lvf\LvfStatsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LvfStatsSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const OWN_CLUB = 710918;

    private const RIVAL_CLUB = 710915;

    private Season $season;

    private Team $own;

    private Team $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::create(['name' => '2025/2026', 'is_current' => true, 'lvf_season_year' => 2025]);

        $this->own = Team::create(['name' => 'Savino Del Bene Volley', 'slug' => 'sdb', 'is_internal' => true, 'lvf_club_id' => self::OWN_CLUB]);
        $this->rival = Team::create(['name' => 'Numia Vero Volley Milano', 'slug' => 'numia', 'is_internal' => false, 'lvf_club_id' => self::RIVAL_CLUB]);

        TeamLvfClubId::create(['team_id' => $this->own->id, 'lvf_club_id' => self::OWN_CLUB]);
        TeamLvfClubId::create(['team_id' => $this->rival->id, 'lvf_club_id' => self::RIVAL_CLUB]);
    }

    private function game(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'season_id' => $this->season->id,
            'home_team_id' => $this->rival->id,
            'away_team_id' => $this->own->id,
            'match_date' => now()->subWeek(),
            'status' => GameStatus::Completed,
            'home_score' => 3,
            'away_score' => 0,
            'lvf_match_id' => 747608,
        ], $attributes));
    }

    /**
     * Tabellino minimo ma con la stessa struttura del reale: tabella-contenitore
     * che ripete l'intestazione, poi la tabella vera con le sotto-colonne.
     */
    private function boxScoreHtml(): string
    {
        $row = fn (int $n, string $name, int $points) => <<<HTML
            <tr><td>{$n}</td><td>{$name}</td><td>1</td><td>1</td><td>1</td><td></td><td></td>
            <td>{$points}</td><td>2</td><td>3</td><td>10</td><td>1</td><td>2</td>
            <td>12</td><td>1</td><td>60%</td><td>30%</td><td>20</td><td>2</td><td>1</td><td>8</td><td>40%</td><td>3</td></tr>
            HTML;

        $header = <<<'HTML'
            <tr><td colspan="2">Squadra</td><td colspan="5">SET</td><td colspan="3">PUNTI</td><td colspan="3">BATTUTA</td><td colspan="4">RICEZIONE</td><td colspan="5">ATTACCO</td><td colspan="2">MURO</td></tr>
            <tr><td colspan="2">Allenatore</td><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>Tot</td><td>BP</td><td>VP</td><td></td><td>Err</td><td>Pt</td><td></td><td>Err</td><td>Pos%</td><td>Prf%</td><td></td><td>Err</td><td>Mur</td><td>Pt</td><td>Pt%</td><td>Pt</td></tr>
            HTML;

        $homeRows = $row(1, 'Miner Kamerynn', 5).$row(2, 'Gelin Juliette', 7);
        $awayRows = $row(3, 'Bosetti Caterina', 9).$row(4, 'Ognjenovic Maja', 4);

        // La tabella-contenitore ripete l'intestazione: è la trappola che faceva
        // attribuire le atlete alla squadra avversaria.
        return <<<HTML
            <html><body>
            <table><tr><td>Data</td><td>Ora</td><td>Spettatori</td><td>Incasso</td></tr>
                   <tr><td>21/2/2026</td><td>20:30</td><td>5.296</td><td></td></tr>
                   <tr><td>Arbitri</td><td>Luca Saltalippi - Massimiliano Giardini</td></tr></table>

            <table><tr><td>Set</td><td>Durata</td><td>Parziali</td><td>Punteggio</td></tr>
                   <tr><td>1</td><td>25'</td><td>8-7</td><td>16-14</td><td>25-20</td></tr>
                   <tr><td>2</td><td>28'</td><td>8-5</td><td>16-12</td><td>25-18</td></tr></table>

            <table><tr><td colspan="2">Numia SET PUNTI BATTUTA ATTACCO</td></tr>
              <tr><td><table>{$header}{$homeRows}</table></td></tr></table>

            <table><tr><td colspan="2">Savino SET PUNTI BATTUTA ATTACCO</td></tr>
              <tr><td><table>{$header}{$awayRows}</table></td></tr></table>
            </body></html>
            HTML;
    }

    private function fakeBoxScore(?string $html = null): void
    {
        Http::swap(new Factory);
        Http::fake(['*TabellinoGara_i.asp*' => Http::response($html ?? $this->boxScoreHtml())]);
    }

    #[Test]
    public function importa_le_statistiche_di_entrambe_le_squadre(): void
    {
        $game = $this->game();
        $this->fakeBoxScore();

        $stats = LvfStatsSyncService::make()->sync($this->season->id);

        $this->assertSame(1, $stats['games']);
        $this->assertSame(4, $stats['rows']);

        $this->assertSame(2, GamePlayerStat::where('team_id', $this->own->id)->count());
        $this->assertSame(2, GamePlayerStat::where('team_id', $this->rival->id)->count());

        $row = GamePlayerStat::where('player_name', 'Bosetti Caterina')->first();
        $this->assertSame($this->own->id, $row->team_id, 'Atleta attribuita alla squadra sbagliata.');
        $this->assertSame(9, $row->points_total);
        $this->assertSame(8, $row->attack_points);
        $this->assertSame(3, $row->block_points);
        $this->assertSame(60, $row->reception_positive_pct);
        $this->assertSame(3, $row->sets_played);

        $game->refresh();
        $this->assertSame(5296, $game->spectators);
        $this->assertSame('Luca Saltalippi - Massimiliano Giardini', $game->referees);
        $this->assertCount(2, $game->set_scores);
        $this->assertNotNull($game->stats_synced_at);
    }

    #[Test]
    public function aggancia_solo_le_nostre_atlete_allanagrafica(): void
    {
        $bosetti = Player::create(['first_name' => 'Caterina', 'last_name' => 'Bosetti']);
        // Un'avversaria omonima di anagrafica non deve essere agganciata: la
        // riga appartiene a un'altra squadra.
        Player::create(['first_name' => 'Kamerynn', 'last_name' => 'Miner']);

        $this->game();
        $this->fakeBoxScore();

        LvfStatsSyncService::make()->sync($this->season->id);

        $this->assertSame(
            $bosetti->id,
            GamePlayerStat::where('player_name', 'Bosetti Caterina')->value('player_id')
        );

        $this->assertNull(
            GamePlayerStat::where('player_name', 'Miner Kamerynn')->value('player_id'),
            "Un'atleta avversaria è stata agganciata all'anagrafica."
        );
    }

    #[Test]
    public function laggancio_ignora_accenti_e_ordine_del_nome(): void
    {
        // La Lega scrive "Cognome Nome" senza accenti; il CMS li usa.
        $maja = Player::create(['first_name' => 'Maja', 'last_name' => 'Ognjenović']);

        $this->game();
        $this->fakeBoxScore();

        LvfStatsSyncService::make()->sync($this->season->id);

        $this->assertSame(
            $maja->id,
            GamePlayerStat::where('player_name', 'Ognjenovic Maja')->value('player_id')
        );
    }

    #[Test]
    public function ricostruisce_lo_storico_di_stagione_delle_nostre_atlete(): void
    {
        $bosetti = Player::create(['first_name' => 'Caterina', 'last_name' => 'Bosetti']);

        $this->game();
        $this->fakeBoxScore();

        LvfStatsSyncService::make()->sync($this->season->id);

        $total = PlayerStat::where('player_id', $bosetti->id)->where('season_id', $this->season->id)->first();

        $this->assertNotNull($total);
        $this->assertSame(9, $total->points);
        $this->assertSame(3, $total->blocks);
        $this->assertSame(2, $total->aces);

        // `attacks` è il totale degli attacchi tentati; i punti realizzati
        // stanno in `attack_points`, così la percentuale è ricostruibile.
        $this->assertSame(20, $total->attacks);
        $this->assertSame(8, $total->attack_points);
        $this->assertSame(2, $total->attack_errors);
        $this->assertSame(1, $total->attack_blocked);
        $this->assertSame(40, $total->attack_pct, 'La percentuale di attacco si ricalcola dai totali, non si media.');

        // Presenze e set: la giocatrice è entrata in 3 set di 1 sola gara.
        $this->assertSame(1, $total->matches_played);
        $this->assertSame(3, $total->sets_played);
        $this->assertSame(60, $total->reception_positive_pct);

        // Le avversarie non entrano nello storico individuale.
        $this->assertSame(1, PlayerStat::count());
    }

    #[Test]
    public function le_percentuali_di_stagione_pesano_le_gare_sul_volume_non_sulla_media(): void
    {
        // Due gare con volumi molto diversi: una ricezione perfetta su 2 palloni
        // non vale quanto un 50% su 40. La media aritmetica darebbe 75%, quella
        // pesata — l'unica corretta — molto meno.
        $bosetti = Player::create(['first_name' => 'Caterina', 'last_name' => 'Bosetti']);

        $prima = $this->game();
        $seconda = $this->game(['lvf_match_id' => 747609]);

        foreach ([[$prima, 2, 100, 20, 20], [$seconda, 40, 50, 60, 30]] as [$game, $ricezioni, $positive, $attacchi, $punti]) {
            GamePlayerStat::create([
                'game_id' => $game->id,
                'team_id' => $this->own->id,
                'player_id' => $bosetti->id,
                'player_name' => 'Bosetti Caterina',
                'sets_played' => 3,
                'points_total' => $punti,
                'reception_total' => $ricezioni,
                'reception_positive_pct' => $positive,
                'attack_total' => $attacchi,
                'attack_points' => $punti,
            ]);
        }

        // Si ricalcola dai referti già in archivio: un `sync()` li riscaricherebbe
        // dal fake, sovrascrivendo le righe costruite qui.
        LvfStatsSyncService::make()->rebuildTotals($this->season->id);

        $total = PlayerStat::where('player_id', $bosetti->id)->first();

        // (100×2 + 50×40) / 42 = 52,4 → 52. La media semplice darebbe 75.
        $this->assertSame(52, $total->reception_positive_pct);
        // Percentuale d'attacco esatta: 50 punti su 80 tentativi.
        $this->assertSame(63, $total->attack_pct);
        $this->assertSame(2, $total->matches_played);
        $this->assertSame(6, $total->sets_played);
    }

    #[Test]
    public function lo_storico_si_ricostruisce_invece_di_sommarsi(): void
    {
        // Un tabellino corretto a posteriori dalla Lega non deve lasciare totali
        // gonfiati: i valori si riscrivono, non si incrementano.
        $bosetti = Player::create(['first_name' => 'Caterina', 'last_name' => 'Bosetti']);

        $this->game();
        $this->fakeBoxScore();

        LvfStatsSyncService::make()->sync($this->season->id);
        $this->fakeBoxScore();
        LvfStatsSyncService::make()->sync($this->season->id, force: true);

        $this->assertSame(9, PlayerStat::where('player_id', $bosetti->id)->value('points'));
    }

    #[Test]
    public function non_scarica_i_tabellini_delle_gare_fra_altre_squadre(): void
    {
        // Un campionato ha 182 gare ma solo 26 sono nostre: scaricarle tutte
        // significherebbe centinaia di richieste inutili alla Lega.
        $other = Team::create(['name' => 'Altra', 'slug' => 'altra', 'is_internal' => false, 'lvf_club_id' => 710999]);

        Game::create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->rival->id,
            'away_team_id' => $other->id,
            'match_date' => now()->subWeek(),
            'status' => GameStatus::Completed,
            'home_score' => 3,
            'away_score' => 1,
            'lvf_match_id' => 999999,
        ]);

        $this->fakeBoxScore();

        $stats = LvfStatsSyncService::make()->sync($this->season->id);

        $this->assertSame(0, $stats['games']);
        $this->assertSame(0, GamePlayerStat::count());
    }

    #[Test]
    public function non_riscarica_un_tabellino_gia_importato(): void
    {
        $this->game();
        $this->fakeBoxScore();
        LvfStatsSyncService::make()->sync($this->season->id);

        $this->fakeBoxScore();
        $stats = LvfStatsSyncService::make()->sync($this->season->id);

        $this->assertSame(0, $stats['games'], 'Il tabellino di una gara conclusa è stato riscaricato senza motivo.');
        Http::assertNothingSent();
    }

    #[Test]
    public function un_tabellino_vuoto_non_viene_marcato_come_importato(): void
    {
        // Capita nelle ore dopo la gara: si deve riprovare al giro successivo.
        $game = $this->game();
        $this->fakeBoxScore('<html><body><p>Tabellino non ancora presente</p></body></html>');

        $stats = LvfStatsSyncService::make()->sync($this->season->id);

        $this->assertSame(0, $stats['games']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertNull($game->fresh()->stats_synced_at);
    }

    #[Test]
    public function una_giocatrice_tolta_dal_referto_sparisce(): void
    {
        $game = $this->game();
        $this->fakeBoxScore();
        LvfStatsSyncService::make()->sync($this->season->id);
        $this->assertSame(4, GamePlayerStat::count());

        GamePlayerStat::create([
            'game_id' => $game->id,
            'team_id' => $this->own->id,
            'player_name' => 'Atleta Rimossa',
        ]);

        $this->fakeBoxScore();
        LvfStatsSyncService::make()->sync($this->season->id, force: true);

        $this->assertSame(4, GamePlayerStat::count());
        $this->assertDatabaseMissing('game_player_stats', ['player_name' => 'Atleta Rimossa']);
    }
}
