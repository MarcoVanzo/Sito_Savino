<?php

namespace App\Http\Controllers;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Http\Controllers\Concerns\PresentaLeSquadre;
use App\Models\Game;
use App\Models\Season;
use App\Models\Standing;
use App\Services\Lvf\LvfPhaseLabel;
use App\Support\LiveStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendario, risultati, classifica e scheda della singola gara.
 *
 * La pagina mostra l'intero campionato, non solo le gare della societa': i
 * risultati delle avversarie decidono la classifica e interessano al tifoso.
 */
class RisultatiController extends Controller
{
    use PresentaLeSquadre;

    public function risultatiCampionato()
    {
        return $this->getRisultatiData(CompetitionType::Championship, __('Campionato Serie A1'), true);
    }

    public function risultatiCev()
    {
        return $this->getRisultatiData(CompetitionType::ChampionsLeague, 'CEV Champions League', false);
    }

    public function risultatiCoppaItalia()
    {
        return $this->getRisultatiData(CompetitionType::CoppaItalia, 'Coppa Italia', false);
    }

    public function risultatiPlayoff()
    {
        return $this->getRisultatiData(CompetitionType::Playoff, 'Playoff', false);
    }

    /**
     * Classifica del campionato come pagina a sé.
     *
     * Nel CMS "Classifica" e "Risultati" sono due voci distinte: qui la
     * classifica è mostrata per intero (quozienti e ripartizione dei set
     * compresi), mentre la pagina Risultati ne tiene solo un estratto.
     */
    public function classifica(): Response
    {
        // Riusa la cache della pagina risultati invece di aprirne una nuova:
        // CacheInvalidationObserver conosce già `public:risultati:*` e la
        // svuota a ogni sincronizzazione della classifica.
        $data = $this->risultatiPayload(CompetitionType::Championship, true);

        return Inertia::render('Public/Classifica', [
            'standings' => $data['standings'],
            'seasonName' => $data['seasonName'],
            'updatedAt' => $this->standingsUpdatedAt($data['standings']),
            'pageTitle' => __('Campionato Serie A1'),
        ]);
    }

    /**
     * Scheda di una singola gara con il tabellino di entrambe le squadre.
     *
     * La pagina esiste solo se la gara è stata giocata e la Lega ne ha già
     * sincronizzato le statistiche: senza tabellino non ci sarebbe nulla da
     * mostrare, quindi si risponde 404 invece di una pagina vuota.
     */
    public function partita(Game $game): Response
    {
        $played = $game->status === GameStatus::Completed
            && $game->home_score !== null
            && $game->away_score !== null;

        if (! $played || ! $game->playerStats()->exists()) {
            abort(404);
        }

        // Nessuna cache applicativa: la scheda costa poche query su una singola
        // gara e una chiave per gara non sarebbe invalidata da
        // CacheInvalidationObserver, che ragiona per modello e non per id.
        return Inertia::render('Public/Partita', $this->presentGameDetail($game));
    }

    private function getRisultatiData(CompetitionType $competitionType, string $pageTitle, bool $showStandings)
    {
        $data = $this->risultatiPayload($competitionType, $showStandings);

        $data['pageTitle'] = $pageTitle;
        $data['showStandings'] = $showStandings;
        $data['competition'] = $competitionType->value;

        return Inertia::render('Public/Risultati', $data);
    }

    /**
     * Gare e classifica di una competizione, cachate per competizione e lingua.
     *
     * La chiave è quella storica `public:risultati:<competizione>:<locale>`:
     * la pagina Classifica riusa lo stesso payload proprio per non introdurre
     * una cache che CacheInvalidationObserver non saprebbe svuotare.
     *
     * @return array{games: list<array<string, mixed>>, standings: list<array<string, mixed>>, seasonName: string}
     */
    private function risultatiPayload(CompetitionType $competitionType, bool $showStandings): array
    {
        $locale = app()->getLocale();
        $cacheKey = "public:risultati:{$competitionType->value}:{$locale}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($competitionType, $showStandings) {
            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            $games = [];
            $standings = [];

            if ($currentSeason) {
                $games = $this->presentGames($currentSeason, $competitionType);

                if ($showStandings) {
                    $standings = $this->presentStandings($currentSeason, $competitionType);
                }
            }

            $seasonName = $currentSeason->name ?? __('Stagione corrente');

            return compact('games', 'standings', 'seasonName');
        });
    }

    /**
     * Gare della stagione nella forma attesa dalla pagina pubblica.
     *
     * L'ordinamento mette in cima le gare già giocate, dalla più recente, e a
     * seguire quelle in calendario, dalla più imminente: la sezione si intitola
     * "ultime partite" ma mostra anche il resto del campionato.
     *
     * La data resta in ISO 8601: la formattazione è localizzata lato client.
     *
     * @return list<array<string, mixed>>
     */
    private function presentGames(Season $season, CompetitionType $competitionType): array
    {
        $games = Game::with(['homeTeam.media', 'awayTeam.media'])
            ->withCount('playerStats')
            ->where('season_id', $season->id)
            ->where('competition_type', $competitionType)
            // Si mostra il campionato per intero, non solo le gare della
            // società: il tifoso segue anche i risultati delle avversarie,
            // che determinano la classifica. Quelle del Savino restano
            // riconoscibili a colpo d'occhio grazie a `isOwn`.
            ->orderBy('matchday')
            ->orderBy('match_date')
            ->get()
            ->map(function (Game $game): array {
                $homeIsOwn = (bool) $game->homeTeam?->is_internal;
                $awayIsOwn = (bool) $game->awayTeam?->is_internal;
                $played = $game->status === GameStatus::Completed
                    && $game->home_score !== null
                    && $game->away_score !== null;

                return [
                    'id' => $game->id,
                    'matchDate' => $game->match_date->toIso8601String(),
                    'home' => $game->homeTeam->name ?? '',
                    'away' => $game->awayTeam->name ?? '',
                    'homeLogo' => $this->teamLogo($game->homeTeam),
                    'awayLogo' => $this->teamLogo($game->awayTeam),
                    'homeIsOwn' => $homeIsOwn,
                    'awayIsOwn' => $awayIsOwn,
                    'scoreHome' => $played ? $game->home_score : null,
                    'scoreAway' => $played ? $game->away_score : null,
                    'played' => $played,
                    // Il link alla scheda gara compare solo dove c'è davvero un
                    // tabellino da leggere: la rotta risponde 404 altrimenti.
                    'hasStats' => $played && $game->player_stats_count > 0,
                    'result' => $this->gameResult($game, $played, $homeIsOwn, $awayIsOwn),
                    'statusLabel' => __('enums.game_status.'.$game->status->value),
                    'matchdayLabel' => $this->matchdayLabel($game),
                    'matchday' => $game->matchday,
                    'phase' => $game->phase,
                    'phaseLabel' => LvfPhaseLabel::translate($game->phase),
                    'location' => $game->location,
                    // Diretta streaming: `streamEmbedUrl` è valorizzato solo per
                    // le piattaforme incorporabili, altrimenti resta il link.
                    'streamUrl' => LiveStream::externalUrl($game->stream_url),
                    'streamEmbedUrl' => LiveStream::embedUrl($game->stream_url),
                    // Vero quando la gara riguarda una squadra della società:
                    // serve a evidenziarla e a filtrare il calendario.
                    'isOwn' => $homeIsOwn || $awayIsOwn,
                ];
            });

        // Ordine cronologico, non per numero di giornata: la Lega numera le
        // giornate da 1 a 13 sia per l'andata sia per il ritorno, quindi
        // ordinando per giornata la 1ª di ritorno (dicembre) finirebbe subito
        // dopo la 1ª di andata (ottobre), spezzando il calendario.
        return $games
            ->sortBy(fn (array $game) => $game['matchDate'])
            ->values()
            ->all();
    }

    /**
     * "3ª Giornata · Andata" quando la Lega espone la giornata, altrimenti
     * si ripiega sullo stato della gara.
     */
    private function matchdayLabel(Game $game): string
    {
        if ($game->matchday === null) {
            return __('enums.game_status.'.$game->status->value);
        }

        $label = __('enums.game.matchday', ['number' => $game->matchday]);

        // `games.phase` è la stringa grezza della Lega, sempre italiana: qui si
        // traduce per la lingua in cui si sta rendendo la pagina.
        $phase = LvfPhaseLabel::translate($game->phase);

        return $phase !== null ? "{$label} · {$phase}" : $label;
    }

    /**
     * Esito dal punto di vista della squadra di casa nostra.
     *
     * Resta null quando la gara non è stata giocata o quando non coinvolge una
     * squadra del club: senza questa distinzione una gara ancora da disputare
     * verrebbe mostrata come sconfitta.
     */
    private function gameResult(Game $game, bool $played, bool $homeIsOwn, bool $awayIsOwn): ?string
    {
        if (! $played || (! $homeIsOwn && ! $awayIsOwn)) {
            return null;
        }

        $ourScore = $homeIsOwn ? $game->home_score : $game->away_score;
        $theirScore = $homeIsOwn ? $game->away_score : $game->home_score;

        return $ourScore > $theirScore ? 'win' : 'loss';
    }

    /**
     * Classifica completa: oltre ai totali espone i quozienti e la ripartizione
     * dei risultati, che la pagina dedicata mostra e quella dei risultati no.
     *
     * @return list<array<string, mixed>>
     */
    private function presentStandings(Season $season, CompetitionType $competitionType): array
    {
        return Standing::with('team.media')
            ->where('season_id', $season->id)
            ->where('competition_type', $competitionType)
            ->ordered()
            ->get()
            ->map(fn (Standing $row): array => [
                'pos' => $row->position,
                'team' => $row->team->name ?? '',
                'logo' => $this->teamLogo($row->team),
                'isOwn' => (bool) $row->team?->is_internal,
                'pts' => $row->points,
                'played' => $row->played,
                'won' => $row->won,
                'lost' => $row->lost,
                'setWon' => $row->sets_won,
                'setLost' => $row->sets_lost,
                'setRatio' => $row->set_ratio,
                'pointRatio' => $row->point_ratio,
                'pointsFor' => $row->points_for,
                'pointsAgainst' => $row->points_against,
                'won30' => $row->won_3_0,
                'won31' => $row->won_3_1,
                'won32' => $row->won_3_2,
                'lost23' => $row->lost_2_3,
                'lost13' => $row->lost_1_3,
                'lost03' => $row->lost_0_3,
                'syncedAt' => $row->synced_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Data dell'ultima sincronizzazione fra le righe di classifica: serve a
     * dichiarare a quando è aggiornata la tabella, invece di lasciarlo intendere.
     *
     * @param  list<array<string, mixed>>  $standings
     */
    private function standingsUpdatedAt(array $standings): ?string
    {
        $dates = array_filter(array_column($standings, 'syncedAt'));

        return $dates === [] ? null : max($dates);
    }

    /**
     * Scheda gara: intestazione, andamento dei set e tabellino delle due
     * squadre, già normalizzati per il frontend.
     *
     * @return array<string, mixed>
     */
    private function presentGameDetail(Game $game): array
    {
        $game->loadMissing(['homeTeam.media', 'awayTeam.media', 'playerStats.player']);

        $homeTeamId = $game->home_team_id;
        $awayTeamId = $game->away_team_id;

        return [
            'game' => [
                'id' => $game->id,
                'matchDate' => $game->match_date->toIso8601String(),
                'competition' => $game->competition_type?->value,
                'competitionLabel' => $game->competition_type?->value,
                'matchdayLabel' => $this->matchdayLabel($game),
                'statusLabel' => __('enums.game_status.'.$game->status->value),
                'location' => $game->location,
                'streamUrl' => LiveStream::externalUrl($game->stream_url),
                'streamEmbedUrl' => LiveStream::embedUrl($game->stream_url),
                'spectators' => $game->spectators,
                'referees' => $game->referees,
                'home' => [
                    'name' => $game->homeTeam->name ?? '',
                    'logo' => $this->teamLogo($game->homeTeam),
                    'isOwn' => (bool) $game->homeTeam?->is_internal,
                    'score' => $game->home_score,
                ],
                'away' => [
                    'name' => $game->awayTeam->name ?? '',
                    'logo' => $this->teamLogo($game->awayTeam),
                    'isOwn' => (bool) $game->awayTeam?->is_internal,
                    'score' => $game->away_score,
                ],
                'sets' => $this->presentSetScores($game),
            ],
            'homeStats' => $this->presentPlayerStats($game, $homeTeamId),
            'awayStats' => $this->presentPlayerStats($game, $awayTeamId),
        ];
    }

    /**
     * Andamento dei set: punteggio finale, parziali intermedi e durata.
     *
     * La Lega esporta sempre cinque set, anche quelli non disputati (durata
     * nulla e parziali "0-0"): quelli vengono scartati, altrimenti la scheda
     * mostrerebbe set mai giocati.
     *
     * @return list<array<string, mixed>>
     */
    private function presentSetScores(Game $game): array
    {
        $raw = is_array($game->set_scores) ? $game->set_scores : [];
        $sets = [];

        foreach ($raw as $index => $entry) {
            $set = is_array($entry) ? $this->presentaUnSet($entry, $index) : null;

            if ($set !== null) {
                $sets[] = $set;
            }
        }

        return $sets;
    }

    /**
     * Un set del referto, se ha un punteggio finale leggibile.
     *
     * Un set 0-0 non e' un set: e' una riga rimasta vuota nel referto.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function presentaUnSet(array $entry, int $index): ?array
    {
        $partials = array_values(array_filter(
            is_array($entry['partials'] ?? null) ? $entry['partials'] : [],
            fn ($partial): bool => is_string($partial) && $this->splitPartial($partial) !== null
        ));

        $final = $partials === [] ? null : $this->splitPartial(end($partials));

        if ($final === null || ($final[0] === 0 && $final[1] === 0)) {
            return null;
        }

        return [
            'set' => isset($entry['set']) && is_numeric($entry['set']) ? (int) $entry['set'] : $index + 1,
            'home' => $final[0],
            'away' => $final[1],
            'duration' => isset($entry['duration']) && is_numeric($entry['duration']) ? (int) $entry['duration'] : null,
            // Solo i parziali intermedi: l'ultimo è già il punteggio del set.
            'partials' => array_slice($partials, 0, -1),
        ];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function splitPartial(string $partial): ?array
    {
        if (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $partial, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * Tabellino di una squadra.
     *
     * Le righe delle avversarie hanno `player_id` nullo: restano nel tabellino
     * (servono per confrontare i numeri) ma senza slug, quindi il frontend non
     * genera un link a una scheda che non esiste.
     *
     * @return list<array<string, mixed>>
     */
    private function presentPlayerStats(Game $game, ?int $teamId): array
    {
        if ($teamId === null) {
            return [];
        }

        return $game->playerStats
            ->where('team_id', $teamId)
            // Numero di maglia crescente, con i senza numero in coda.
            ->sortBy(fn ($stat): string => sprintf(
                '%d%04d',
                $stat->jersey_number === null ? 1 : 0,
                $stat->jersey_number ?? 0
            ))
            ->map(fn ($stat): array => [
                'id' => $stat->id,
                'jersey' => $stat->jersey_number,
                'name' => $stat->player->full_name ?? $stat->player_name,
                'playerSlug' => $stat->player
                    ? $stat->player->id.'-'.Str::slug($stat->player->full_name)
                    : null,
                'isCaptain' => (bool) $stat->is_captain,
                'isLibero' => (bool) $stat->is_libero,
                'setsPlayed' => $stat->sets_played,
                'points' => $stat->points_total,
                'attackPoints' => $stat->attack_points,
                'blockPoints' => $stat->block_points,
                'servePoints' => $stat->serve_points,
                'attackPct' => $stat->attack_pct,
                'receptionPositivePct' => $stat->reception_positive_pct,
                'receptionPerfectPct' => $stat->reception_perfect_pct,
            ])
            ->values()
            ->all();
    }
}
