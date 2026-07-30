<?php

namespace App\Services\Lvf;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Services\Lvf\Data\LvfPlayerStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa i tabellini delle gare e ne ricava lo storico delle nostre atlete.
 *
 * Si scaricano SOLO i tabellini delle gare che coinvolgono una squadra del club:
 * sono 26 su 182 in un campionato, e scaricarli tutti significherebbe centosessanta
 * richieste inutili al sito della Lega a ogni giro.
 *
 * Le righe delle avversarie vengono comunque salvate — servono nella scheda della
 * partita — ma senza `player_id`, quindi non entrano nello storico individuale.
 */
class LvfStatsSyncService
{
    public function __construct(
        private readonly LvfClient $client,
        private readonly LvfBoxScoreParser $parser,
    ) {}

    public static function make(): self
    {
        return new self(LvfClient::fromConfig(), new LvfBoxScoreParser);
    }

    /**
     * @return array{games: int, rows: int, matched: int, skipped: int}
     */
    public function sync(?int $seasonId = null, bool $force = false): array
    {
        $stats = ['games' => 0, 'rows' => 0, 'matched' => 0, 'skipped' => 0];

        foreach ($this->gamesToSync($seasonId, $force) as $game) {
            try {
                $result = $this->syncGame($game);
            } catch (Throwable $e) {
                // Un tabellino non ancora pubblicato non deve fermare gli altri.
                Log::warning("Tabellino gara #{$game->lvf_match_id} non importato: {$e->getMessage()}");
                $stats['skipped']++;

                continue;
            }

            if ($result === null) {
                $stats['skipped']++;

                continue;
            }

            $stats['games']++;
            $stats['rows'] += $result['rows'];
            $stats['matched'] += $result['matched'];
        }

        $this->rebuildSeasonTotals($seasonId);

        return $stats;
    }

    /**
     * Gare candidate: giocate, importate dalla Lega e che coinvolgono il club.
     *
     * @return Collection<int, Game>
     */
    private function gamesToSync(?int $seasonId, bool $force): Collection
    {
        return Game::query()
            ->whereNotNull('lvf_match_id')
            ->where('status', GameStatus::Completed)
            ->when($seasonId !== null, fn ($query) => $query->where('season_id', $seasonId))
            ->where(function ($query) {
                $query->whereHas('homeTeam', fn ($team) => $team->where('is_internal', true))
                    ->orWhereHas('awayTeam', fn ($team) => $team->where('is_internal', true));
            })
            // Senza --force si riscaricano solo i tabellini mai importati: quelli
            // di una gara conclusa non cambiano più.
            ->when(! $force, fn ($query) => $query->whereNull('stats_synced_at'))
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date')
            ->get();
    }

    /**
     * @return array{rows: int, matched: int}|null
     */
    private function syncGame(Game $game): ?array
    {
        $homeClubId = $game->homeTeam?->lvf_club_id;
        $awayClubId = $game->awayTeam?->lvf_club_id;

        if ($homeClubId === null || $awayClubId === null) {
            return null;
        }

        $box = $this->parser->parse(
            $this->client->boxScore((int) $game->lvf_match_id),
            (int) $game->lvf_match_id,
            (int) $homeClubId,
            (int) $awayClubId,
        );

        if ($box->isEmpty()) {
            // Il tabellino esiste ma è ancora vuoto: si riproverà al giro dopo,
            // quindi `stats_synced_at` resta null di proposito.
            return null;
        }

        $ownPlayers = $this->ownPlayersByName();
        $rows = 0;
        $matched = 0;

        DB::transaction(function () use ($game, $box, $homeClubId, $ownPlayers, &$rows, &$matched) {
            $keep = [];

            foreach ($box->players as $player) {
                // Le chiavi squadra della gara sono NOT NULL con vincolo di
                // integrità: la relazione è sempre valorizzata.
                $team = $player->clubId === $homeClubId ? $game->homeTeam : $game->awayTeam;

                // Solo per le nostre squadre si tenta l'aggancio all'anagrafica.
                $playerId = $team->is_internal
                    ? ($ownPlayers[$this->nameKey($player->playerName)] ?? null)
                    : null;

                if ($playerId !== null) {
                    $matched++;
                }

                $record = GamePlayerStat::updateOrCreate(
                    [
                        'game_id' => $game->id,
                        'team_id' => $team->id,
                        'player_name' => $player->playerName,
                    ],
                    $this->attributes($player, $playerId),
                );

                $keep[] = $record->id;
                $rows++;
            }

            // Una giocatrice tolta dal referto non deve restare in archivio.
            GamePlayerStat::where('game_id', $game->id)->whereNotIn('id', $keep)->delete();

            $game->forceFill([
                'spectators' => $box->spectators ?? $game->spectators,
                'referees' => $box->referees ?? $game->referees,
                'set_scores' => $box->sets !== [] ? $box->sets : $game->set_scores,
                'stats_synced_at' => now(),
            ])->save();
        });

        return ['rows' => $rows, 'matched' => $matched];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(LvfPlayerStat $player, ?int $playerId): array
    {
        return [
            'player_id' => $playerId,
            'jersey_number' => $player->jerseyNumber,
            'is_captain' => $player->isCaptain,
            'is_libero' => $player->isLibero,
            'sets_played' => $player->setsPlayed,
            'points_total' => $player->pointsTotal,
            'points_break' => $player->pointsBreak,
            'points_win_loss' => $player->pointsWinLoss,
            'serve_total' => $player->serveTotal,
            'serve_errors' => $player->serveErrors,
            'serve_points' => $player->servePoints,
            'reception_total' => $player->receptionTotal,
            'reception_errors' => $player->receptionErrors,
            'reception_positive_pct' => $player->receptionPositivePct,
            'reception_perfect_pct' => $player->receptionPerfectPct,
            'attack_total' => $player->attackTotal,
            'attack_errors' => $player->attackErrors,
            'attack_blocked' => $player->attackBlocked,
            'attack_points' => $player->attackPoints,
            'attack_pct' => $player->attackPct,
            'block_points' => $player->blockPoints,
            'synced_at' => now(),
        ];
    }

    /**
     * Indice delle nostre atlete per nome normalizzato.
     *
     * La Lega scrive "Cognome Nome", il CMS tiene i due campi separati: si
     * indicizzano entrambi gli ordini, perché su nomi composti indovinare quale
     * parte sia il cognome non è affidabile.
     *
     * @return array<string, int>
     */
    private function ownPlayersByName(): array
    {
        $index = [];

        foreach (Player::query()->get(['id', 'first_name', 'last_name']) as $player) {
            foreach ([
                $player->last_name.' '.$player->first_name,
                $player->first_name.' '.$player->last_name,
            ] as $variant) {
                $index[$this->nameKey($variant)] ??= $player->id;
            }
        }

        return $index;
    }

    /**
     * Chiave di confronto insensibile ad accenti, maiuscole e spazi doppi.
     */
    private function nameKey(string $name): string
    {
        return Str::of($name)->ascii()->lower()->squish()->replace(['.', "'", '-'], ' ')->squish()->value();
    }

    /**
     * Ricalcola i totali di stagione delle nostre atlete a partire dalle gare.
     *
     * `player_stats` è la tabella storica già usata dal sito: si riscrive per
     * intero dai tabellini invece di incrementarla, così un tabellino corretto a
     * posteriori dalla Lega non lascia totali gonfiati.
     */
    /**
     * Ricalcola i totali di stagione dai tabellini già in archivio, senza
     * ripassare dal sito della Lega.
     *
     * Serve quando cambia il modo di aggregare — o quando si corregge un
     * errore di calcolo — senza dover riscaricare centinaia di referti.
     */
    public function rebuildTotals(?int $seasonId = null): int
    {
        $this->rebuildSeasonTotals($seasonId);

        return PlayerStat::query()
            ->when($seasonId !== null, fn ($query) => $query->where('season_id', $seasonId))
            ->count();
    }

    private function rebuildSeasonTotals(?int $seasonId): void
    {
        // Query builder e non Eloquent: qui si aggrega, non si recuperano
        // modelli, e le colonne calcolate non sono attributi di GamePlayerStat.
        $totals = DB::table('game_player_stats')
            ->whereNotNull('game_player_stats.player_id')
            ->join('games', 'games.id', '=', 'game_player_stats.game_id')
            ->when($seasonId !== null, fn ($query) => $query->where('games.season_id', $seasonId))
            // Per squadra oltre che per stagione: un'atleta schierata sia in A1
            // sia in B1 ha due bilanci distinti, non uno sommato.
            ->groupBy('game_player_stats.player_id', 'games.season_id', 'game_player_stats.team_id')
            ->selectRaw('game_player_stats.player_id, games.season_id, game_player_stats.team_id')
            // Presenze: solo le gare in cui è scesa in campo, non quelle a referto.
            ->selectRaw('COUNT(DISTINCT CASE WHEN sets_played > 0 THEN game_id END) as matches_played')
            ->selectRaw('SUM(sets_played) as sets_played')
            ->selectRaw('SUM(points_total) as points')
            ->selectRaw('SUM(points_break) as points_break')
            ->selectRaw('SUM(block_points) as blocks')
            ->selectRaw('SUM(serve_points) as aces')
            ->selectRaw('SUM(serve_total) as serve_total')
            ->selectRaw('SUM(serve_errors) as serve_errors')
            ->selectRaw('SUM(attack_total) as attack_total')
            ->selectRaw('SUM(attack_errors) as attack_errors')
            ->selectRaw('SUM(attack_blocked) as attack_blocked')
            ->selectRaw('SUM(attack_points) as attack_points')
            ->selectRaw('SUM(reception_total) as receptions')
            ->selectRaw('SUM(reception_errors) as reception_errors')
            // Le percentuali di ricezione non sono sommabili: la Lega pubblica
            // solo la percentuale per gara, mai il conteggio assoluto delle
            // positive. Si pesa ciascuna sul volume di ricezione di quella gara,
            // che è la migliore ricostruzione possibile del dato di stagione.
            ->selectRaw('SUM(reception_positive_pct * reception_total) as weighted_positive')
            ->selectRaw('SUM(reception_perfect_pct * reception_total) as weighted_perfect')
            ->get();

        foreach ($totals as $row) {
            $attackTotal = (int) $row->attack_total;
            $receptionTotal = (int) $row->receptions;

            PlayerStat::updateOrCreate(
                [
                    'player_id' => $row->player_id,
                    'season_id' => $row->season_id,
                    'team_id' => $row->team_id,
                ],
                [
                    'matches_played' => (int) $row->matches_played,
                    'sets_played' => (int) $row->sets_played,
                    'points' => (int) $row->points,
                    'points_break' => (int) $row->points_break,
                    'blocks' => (int) $row->blocks,
                    'aces' => (int) $row->aces,
                    'serve_total' => (int) $row->serve_total,
                    'serve_errors' => (int) $row->serve_errors,
                    // `attacks` resta il totale degli attacchi tentati, come
                    // altrove nel progetto; i punti realizzati sono a parte.
                    'attacks' => $attackTotal,
                    'attack_errors' => (int) $row->attack_errors,
                    'attack_blocked' => (int) $row->attack_blocked,
                    'attack_points' => (int) $row->attack_points,
                    // Questa invece è esatta: entrambi i termini sono assoluti.
                    'attack_pct' => $attackTotal > 0
                        ? (int) round((int) $row->attack_points * 100 / $attackTotal)
                        : null,
                    'receptions' => $receptionTotal,
                    'reception_errors' => (int) $row->reception_errors,
                    'reception_positive_pct' => $receptionTotal > 0
                        ? (int) round((float) $row->weighted_positive / $receptionTotal)
                        : null,
                    'reception_perfect_pct' => $receptionTotal > 0
                        ? (int) round((float) $row->weighted_perfect / $receptionTotal)
                        : null,
                    'last_synced_at' => now(),
                ]
            );
        }
    }
}
