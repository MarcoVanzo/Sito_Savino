<?php

namespace App\Http\Controllers;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Enums\StaffType;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Roster;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Models\StaffMember;
use App\Models\Standing;
use App\Models\Team;
use App\Services\Lvf\LvfPhaseLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    /**
     * Foto incluse nel payload iniziale della gallery. Il resto dell'archivio
     * viene caricato dopo, da `galleryData()`.
     */
    private const GALLERY_INITIAL_CHUNK = 120;

    public function home()
    {
        $locale = app()->getLocale();
        $data = Cache::remember("public:home:{$locale}", now()->addMinutes(5), function () {
            // Prossima partita programmata **della società**: il calendario
            // importato contiene tutto il campionato, quindi senza il vincolo
            // sulla squadra interna la home mostrava la prossima gara di due
            // avversarie.
            $nextGameModel = Game::with(['homeTeam', 'awayTeam'])
                ->where('status', GameStatus::Scheduled)
                ->where('match_date', '>=', now())
                ->where(function ($query) {
                    $query->whereHas('homeTeam', fn ($team) => $team->where('is_internal', true))
                        ->orWhereHas('awayTeam', fn ($team) => $team->where('is_internal', true));
                })
                ->orderBy('match_date')
                ->first();

            $nextGame = $nextGameModel?->toArray();

            if ($nextGame !== null) {
                // I loghi vanno letti da Team::logoUrl(), non scelti nel
                // template: la home mostrava lo stemma del Savino su qualunque
                // squadra di casa.
                $nextGame['home_team']['logo_url'] = $nextGameModel->homeTeam?->logoUrl();
                $nextGame['away_team']['logo_url'] = $nextGameModel->awayTeam?->logoUrl();
            }

            // Ultime 3 news pubblicate
            $latestNews = Post::published()
                ->with('media')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->take(3)
                ->get()
                ->map(fn ($post) => [
                    'id' => $post->id,
                    'title' => $post->getTranslation('title', app()->getLocale(), false),
                    'slug' => $post->slug,
                    'excerpt' => $post->getTranslation('excerpt', app()->getLocale(), false),
                    'published_at' => $post->published_at?->toISOString(),
                    'image_url' => $post->getFirstMediaUrl('cover', 'card') ?: $post->getFirstMediaUrl('cover'),
                ])->toArray();

            $heroSlides = HeroSlide::active()->ordered()->with('media')->get()
                ->map(fn ($slide) => [
                    'id' => $slide->id,
                    'title' => $slide->title,
                    'subtitle' => $slide->subtitle,
                    'image' => $slide->getFirstMediaUrl('hero-slides', 'hero') ?: $slide->getFirstMediaUrl('hero-slides') ?: '/images/hero1.jpg',
                    'cta_text' => $slide->cta_text,
                    'cta_url' => $slide->cta_url,
                ])->toArray();

            return [
                'nextGame' => $nextGame,
                'latestNews' => $latestNews,
                'heroSlides' => $heroSlides,
            ];
        });

        return Inertia::render('Public/Home', $data);
    }

    public function stagione()
    {
        $locale = app()->getLocale();

        return $this->stagioneForTeam('savino-del-bene-volley', "public:stagione:{$locale}");
    }

    public function stagioneB1()
    {
        $locale = app()->getLocale();

        return $this->stagioneForTeam('serie-b1', "public:stagione:b1:{$locale}", 'Serie B1');
    }

    /**
     * Logica condivisa per il caricamento roster di un team specifico.
     */
    private function stagioneForTeam(string $teamSlug, string $cacheKey, ?string $teamLabel = null): Response
    {
        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($teamSlug) {
            $team = Team::with('media')
                ->where('slug', $teamSlug)
                ->orWhere('category', $teamSlug === 'savino-del-bene-volley' ? 'A1' : 'B1')
                ->first();

            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            $roster = [];
            $seasonName = null;
            $seasonStats = [];
            $teamInfo = null;

            if ($team && $currentSeason) {
                $rosterEntries = Roster::with([
                    'player',
                    'media',
                    // I totali sono per stagione E per squadra: senza il secondo
                    // filtro un'atleta schierata anche in un'altra squadra della
                    // società porterebbe qui il bilancio sbagliato. Le righe
                    // storiche senza squadra restano incluse, altrimenti la
                    // pagina perderebbe i dati importati prima del campo.
                    'player.stats' => fn ($query) => $query
                        ->where('season_id', $currentSeason->id)
                        ->where(fn ($scoped) => $scoped->whereNull('team_id')->orWhere('team_id', $team->id)),
                ])
                    ->whereHas('player')
                    ->where('team_id', $team->id)
                    ->where('season_id', $currentSeason->id)
                    ->orderByRaw('jersey_number IS NULL, jersey_number')
                    ->orderBy('id')
                    ->get();

                $roster = $rosterEntries->toArray();
                $seasonStats = $this->presentSeasonStats($rosterEntries, $team->id);
                $seasonName = $currentSeason->name;

                $teamInfo = [
                    'name' => $team->name,
                    'logo' => $this->teamLogo($team),
                ];
            }

            // Staff tecnico e medico filtrati per sezione (A1 vs Youth)
            $section = $teamSlug === 'savino-del-bene-volley' ? 'a1' : 'youth';

            $mapStaff = fn ($p) => [
                'id' => $p->id,
                'name' => $p->full_name,
                'role' => $p->role,
                'photo_url' => $p->getFirstMediaUrl('staff', 'card') ?: $p->getFirstMediaUrl('staff'),
            ];

            $staffTecnico = StaffMember::with('media')
                ->where('type', StaffType::Tecnico)
                ->where(function ($q) use ($section) {
                    if ($section === 'a1') {
                        $q->where('section', 'a1')->orWhereNull('section');
                    } else {
                        $q->where('section', $section);
                    }
                })
                ->orderBy('sort_order')->orderBy('id')->get()->map($mapStaff)->toArray();

            $staffMedico = StaffMember::with('media')
                ->where('type', StaffType::Medico)
                ->where(function ($q) use ($section) {
                    if ($section === 'a1') {
                        $q->where('section', 'a1')->orWhereNull('section');
                    } else {
                        $q->where('section', $section);
                    }
                })
                ->orderBy('sort_order')->orderBy('id')->get()->map($mapStaff)->toArray();

            return compact('roster', 'seasonName', 'seasonStats', 'teamInfo', 'staffTecnico', 'staffMedico');
        });

        return Inertia::render('Public/Stagione', $teamLabel ? array_merge($data, ['teamLabel' => $teamLabel]) : $data);
    }

    /**
     * Totali di stagione della rosa, pronti per la tabella comparativa.
     *
     * Le righe arrivano dalle relazioni già caricate con la rosa: nessuna query
     * aggiuntiva, la pagina resta cachabile com'è.
     *
     * Compaiono solo le atlete che hanno un bilancio in archivio, e la tabella
     * intera sparisce se nessuna ha numeri diversi da zero: le giovanili non
     * hanno tabellini della Lega e una griglia di zeri direbbe il falso.
     *
     * @param  Collection<int, Roster>  $rosterEntries
     * @return list<array<string, mixed>>
     */
    private function presentSeasonStats(Collection $rosterEntries, int $teamId): array
    {
        $rows = [];

        foreach ($rosterEntries as $entry) {
            $player = $entry->player;

            if (! $player instanceof Player) {
                continue;
            }

            // Solo la relazione già caricata: leggerla dal modello scatenerebbe
            // una query per atleta su una pagina che ne mostra quattordici.
            $stats = $player->relationLoaded('stats') ? $player->getRelation('stats') : null;

            if (! $stats instanceof Collection) {
                continue;
            }

            // Con più righe disponibili vince quella della squadra di questa
            // pagina; la riga storica senza squadra è solo un ripiego.
            $stat = $stats->firstWhere('team_id', $teamId) ?? $stats->first();

            if (! $stat instanceof PlayerStat) {
                continue;
            }

            $rows[] = [
                'id' => $entry->id,
                'jersey' => $entry->jersey_number,
                'name' => $player->full_name,
                'role' => $entry->role,
                'playerSlug' => $player->id.'-'.Str::slug($player->full_name),
                'matchesPlayed' => (int) $stat->matches_played,
                'setsPlayed' => (int) $stat->sets_played,
                'points' => (int) $stat->points,
                'pointsPerSet' => $stat->pointsPerSet(),
                // `attacks` sono gli attacchi tentati: i punti realizzati sono
                // `attack_points`, ed è quello che la tabella chiama "Attacco".
                'attackPoints' => (int) $stat->attack_points,
                'attackPct' => $stat->attack_pct,
                'blocks' => (int) $stat->blocks,
                'aces' => (int) $stat->aces,
                'receptions' => (int) $stat->receptions,
                'receptionPositivePct' => $stat->reception_positive_pct,
                'receptionPerfectPct' => $stat->reception_perfect_pct,
            ];
        }

        foreach ($rows as $row) {
            if ($row['matchesPlayed'] > 0
                || $row['setsPlayed'] > 0
                || $row['points'] > 0
                || $row['attackPoints'] > 0
                || $row['blocks'] > 0
                || $row['aces'] > 0
                || $row['receptions'] > 0
            ) {
                return $rows;
            }
        }

        return [];
    }

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
        return $this->getRisultatiData(CompetitionType::CoppaItalia, 'Coppa Italia & Playoff', false);
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
                    'matchDate' => $game->match_date?->toIso8601String(),
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
                    'statusLabel' => __('enums.game_status.'.$game->status?->value),
                    'matchdayLabel' => $this->matchdayLabel($game),
                    'matchday' => $game->matchday,
                    'phase' => $game->phase,
                    'phaseLabel' => LvfPhaseLabel::translate($game->phase),
                    'location' => $game->location,
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
            return __('enums.game_status.'.$game->status?->value);
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
     * Logo della squadra secondo la precedenza definita da Team::logoUrl()
     * (CMS → import Lega → URL remoto). Mai `logo_url` diretto.
     */
    private function teamLogo(?Team $team): ?string
    {
        $logo = $team?->logoUrl();

        return ($logo === null || $logo === '') ? null : $logo;
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
                'matchDate' => $game->match_date?->toIso8601String(),
                'competition' => $game->competition_type?->value,
                'competitionLabel' => $game->competition_type?->value,
                'matchdayLabel' => $this->matchdayLabel($game),
                'statusLabel' => __('enums.game_status.'.$game->status?->value),
                'location' => $game->location,
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
            if (! is_array($entry)) {
                continue;
            }

            $partials = array_values(array_filter(
                is_array($entry['partials'] ?? null) ? $entry['partials'] : [],
                fn ($partial): bool => is_string($partial) && $this->splitPartial($partial) !== null
            ));

            $final = $partials === [] ? null : $this->splitPartial(end($partials));

            if ($final === null || ($final[0] === 0 && $final[1] === 0)) {
                continue;
            }

            $sets[] = [
                'set' => isset($entry['set']) && is_numeric($entry['set']) ? (int) $entry['set'] : $index + 1,
                'home' => $final[0],
                'away' => $final[1],
                'duration' => isset($entry['duration']) && is_numeric($entry['duration']) ? (int) $entry['duration'] : null,
                // Solo i parziali intermedi: l'ultimo è già il punteggio del set.
                'partials' => array_slice($partials, 0, -1),
            ];
        }

        return $sets;
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

    public function fotoUfficiale()
    {
        $pdfUrl = SiteSetting::get('official_photo_pdf');

        if ($pdfUrl) {
            return redirect(Storage::url($pdfUrl));
        }

        // `back()` seguiva l'URL precedente in sessione e poteva scaricare il
        // visitatore su una rotta interna qualunque (un endpoint JSON, una
        // pagina del pannello). Si torna sempre alla stagione, dove il link
        // vive, e il messaggio resta visibile.
        return redirect()
            ->route(app()->getLocale() === 'it' ? 'stagione' : app()->getLocale().'.stagione')
            ->with('error', __('Foto ufficiale non ancora caricata.'));
    }

    public function gallery()
    {
        return $this->renderGallery();
    }

    public function galleryAtleta(string $slug)
    {
        $id = explode('-', $slug)[0];
        $player = Player::findOrFail($id);

        return $this->renderGallery($player);
    }

    private function renderGallery(?Player $playerFilter = null)
    {
        $locale = app()->getLocale();
        $page = Cache::remember("public:page:gallery:{$locale}", now()->addMinutes(30), function () {
            return Page::where('slug', 'gallery')->published()->first();
        });

        $media = $this->galleryMedia($playerFilter);

        // Get all players that have at least one gallery image for the filter dropdown
        $athletes = Cache::remember("public:gallery_athletes:{$locale}", now()->addMinutes(30), function () {
            return Player::whereHas('galleryImages', fn ($q) => $q->where('gallery_images.is_active', true))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'slug' => $p->id.'-'.Str::slug($p->full_name),
                ])->toArray();
        });

        $totalEvents = Cache::remember('public:gallery_total_events', now()->addMinutes(30), function () {
            return GalleryEvent::where('is_active', true)
                ->whereHas('galleryImages', fn ($q) => $q->where('is_active', true))
                ->count();
        });

        return Inertia::render('Public/Gallery', [
            'page' => $page,
            // Solo il primo blocco: l'archivio è di ~900 foto e serializzarlo
            // tutto portava la pagina a mezzo megabyte di HTML. Il resto arriva
            // da /gallery/data appena la pagina è interattiva, così i filtri
            // continuano a lavorare sull'archivio completo.
            'media' => array_slice($media, 0, self::GALLERY_INITIAL_CHUNK),
            'mediaTotal' => count($media),
            'athletes' => $athletes,
            'totalEvents' => $totalEvents,
            'currentAthlete' => $playerFilter ? [
                'id' => $playerFilter->id,
                'name' => $playerFilter->full_name,
                'slug' => $playerFilter->id.'-'.Str::slug($playerFilter->full_name),
            ] : null,
        ]);
    }

    /**
     * Archivio completo della gallery, normalizzato per il front-end.
     *
     * @return list<array<string, mixed>>
     */
    private function galleryMedia(?Player $playerFilter = null): array
    {
        $locale = app()->getLocale();

        $cacheKey = $playerFilter
            ? "public:gallery_images:player_{$playerFilter->id}:{$locale}"
            : "public:gallery_images:{$locale}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($playerFilter, $locale) {
            $query = GalleryImage::active()->ordered()
                ->with(['media', 'players:id,first_name,last_name', 'galleryEvent:id,title']);

            if ($playerFilter) {
                $query->whereHas('players', function ($q) use ($playerFilter) {
                    $q->where('players.id', $playerFilter->id);
                });
            }

            return $query->get()
                ->map(function ($img) use ($locale) {
                    $decodeTitle = function ($text) use ($locale) {
                        if (! is_string($text) || ! str_starts_with($text, '{"it":')) {
                            return $text;
                        }
                        $decoded = json_decode($text, true);
                        if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                            return $decoded[$locale] ?? $decoded['it'];
                        }
                        // Attempt to fix truncated JSON
                        $decoded = json_decode($text.'"}', true);
                        if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                            return $decoded[$locale] ?? $decoded['it'];
                        }
                        $decoded = json_decode($text.'}', true);
                        if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                            return $decoded[$locale] ?? $decoded['it'];
                        }

                        return $text;
                    };

                    $altText = $decodeTitle($img->title ?? __('Immagine Galleria'));
                    $eventName = $decodeTitle($img->galleryEvent?->title);

                    return [
                        'id' => $img->id,
                        'url' => $img->getFirstMediaUrl('gallery', 'lightbox') ?: $img->getFirstMediaUrl('gallery'),
                        'thumb' => $img->getFirstMediaUrl('gallery', 'thumb') ?: $img->getFirstMediaUrl('gallery'),
                        'alt' => mb_substr($altText, 0, 255),
                        'category' => $img->category ?? 'Partite',
                        'tags' => $img->players->map(fn ($p) => $p->full_name)->values()->toArray(),
                        'event_name' => $eventName,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Archivio completo della gallery in JSON, per il caricamento differito.
     */
    public function galleryData(?string $slug = null)
    {
        $playerFilter = null;

        if ($slug) {
            $playerId = (int) explode('-', $slug)[0];
            $playerFilter = Player::find($playerId);

            if (! $playerFilter) {
                abort(404);
            }
        }

        return response()->json([
            'media' => $this->galleryMedia($playerFilter),
        ]);
    }

    public function staff()
    {
        $locale = app()->getLocale();
        $staffTecnico = Cache::remember("public:staff_tecnico:{$locale}", now()->addMinutes(30), function () {
            return StaffMember::with('media')
                ->where('type', StaffType::Tecnico)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'role' => $p->role,
                    'photo_url' => $p->getFirstMediaUrl('staff', 'card') ?: $p->getFirstMediaUrl('staff'),
                ])
                ->toArray();
        });

        $staffMedico = Cache::remember("public:staff_medico:{$locale}", now()->addMinutes(30), function () {
            return StaffMember::with('media')
                ->where('type', StaffType::Medico)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'role' => $p->role,
                    'photo_url' => $p->getFirstMediaUrl('staff', 'card') ?: $p->getFirstMediaUrl('staff'),
                ])
                ->toArray();
        });

        return Inertia::render('Public/Staff', [
            'staffTecnico' => $staffTecnico,
            'staffMedico' => $staffMedico,
        ]);
    }

    public function sponsor()
    {
        $locale = app()->getLocale();
        $sponsors = Cache::remember("public:sponsor:{$locale}", now()->addMinutes(30), function () {
            return Sponsor::with('media')
                ->orderBy('tier')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'tier' => $s->tier,
                    'website_url' => $s->url,
                    'logo_url' => $s->getFirstMediaUrl('sponsors', 'card') ?: $s->getFirstMediaUrl('sponsors'),
                    'sort_order' => $s->sort_order,
                ])->toArray();
        });

        $page = Page::where('slug', 'sponsor')->published()->first();

        return Inertia::render('Public/Sponsor', [
            'sponsors' => $sponsors,
            'page' => $page,
        ]);
    }

    public function contatti()
    {
        $page = Page::where('slug', 'contatti')->published()->first();

        return Inertia::render('Public/Contatti', [
            'page' => $page,
        ]);
    }

    public function underConstruction()
    {
        return Inertia::render('Public/UnderConstruction');
    }
}
