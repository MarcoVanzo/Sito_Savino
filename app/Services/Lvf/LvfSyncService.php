<?php

namespace App\Services\Lvf;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use App\Models\TeamLvfClubId;
use App\Services\Lvf\Data\LvfMatch;
use App\Services\Lvf\Data\LvfStandingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa calendario, risultati e classifica dal sito della Lega.
 *
 * Due invarianti governano tutto il servizio:
 *
 * 1. È idempotente. Ogni entità remota ha un identificativo stabile
 *    (`lvf_match_id`, `lvf_club_id`) su cui si fa upsert, quindi rilanciare la
 *    sincronizzazione non duplica nulla.
 * 2. Non tocca il lavoro fatto a mano. Le gare inserite dal CMS non hanno un
 *    `lvf_match_id` e non vengono mai modificate né cancellate: la Lega può solo
 *    aggiungere e aggiornare le proprie.
 */
class LvfSyncService
{
    public function __construct(
        private readonly LvfClient $client,
        private readonly LvfMatchParser $matchParser,
        private readonly LvfStandingsParser $standingsParser,
    ) {}

    /**
     * Anno della stagione in corso di importazione: serve a registrare a quale
     * stagione appartiene ciascun identificativo di club.
     */
    private ?int $seasonYear = null;

    public static function make(): self
    {
        return new self(LvfClient::fromConfig(), new LvfMatchParser, new LvfStandingsParser);
    }

    /**
     * Sincronizza gare e classifica di una stagione.
     *
     * @return array{matches_created: int, matches_updated: int, matches_removed: int, teams_created: int, standings: int}
     */
    public function sync(int $seasonYear): array
    {
        $this->seasonYear = $seasonYear;
        $season = $this->resolveSeason($seasonYear);

        $stats = ['matches_created' => 0, 'matches_updated' => 0, 'matches_removed' => 0, 'teams_created' => 0, 'standings' => 0];

        // Il calendario porta giornata, fase e impianto; la pagina risultati
        // porta i set. Le gare compaiono su entrambe, quindi si fondono per
        // identificativo dando la precedenza ai dati più completi.
        $matches = $this->mergeMatches(
            $this->matchParser->parse($this->client->calendar($seasonYear)),
            $this->matchParser->parse($this->client->results($seasonYear)),
        );

        // Le pagine della Lega elencano A1 e A2 insieme: senza questo filtro il
        // campionato pubblicato conteneva 454 gare e 31 squadre, con giornate
        // fino alla 17ª, mentre la classifica restava (correttamente) di sole 14
        // righe. Le gare scartate spariscono da sole al giro successivo, perché
        // removeVanishedMatches pota quelle non più viste.
        $matches = array_values(array_filter(
            $matches,
            fn (LvfMatch $match) => $this->belongsToTrackedDivision($match->competition)
        ));

        $teamsBefore = Team::count();

        $seenMatchIds = [];

        foreach ($matches as $match) {
            $created = $this->upsertMatch($season, $match);
            $seenMatchIds[] = $match->lvfMatchId;
            $stats[$created ? 'matches_created' : 'matches_updated']++;
        }

        $stats['matches_removed'] = $this->removeVanishedMatches($season, $seenMatchIds);

        $rows = $this->standingsParser->parse($this->client->standings($seasonYear));
        $stats['standings'] = $this->upsertStandings($season, $rows);

        // Contato una volta sola a fine corsa: comprende anche le squadre
        // incontrate solo in classifica.
        $stats['teams_created'] = Team::count() - $teamsBefore;

        return $stats;
    }

    /**
     * Fonde le gare provenienti dalle due pagine.
     *
     * @param  list<LvfMatch>  $calendar
     * @param  list<LvfMatch>  $results
     * @return list<LvfMatch>
     */
    private function mergeMatches(array $calendar, array $results): array
    {
        /** @var array<int, LvfMatch> $merged */
        $merged = [];

        foreach ($calendar as $match) {
            $merged[$match->lvfMatchId] = $match;
        }

        foreach ($results as $match) {
            $existing = $merged[$match->lvfMatchId] ?? null;

            if ($existing === null) {
                $merged[$match->lvfMatchId] = $match;

                continue;
            }

            // Il calendario non espone i set: si prendono dai risultati,
            // mantenendo i metadati che solo il calendario possiede.
            $merged[$match->lvfMatchId] = new LvfMatch(
                lvfMatchId: $existing->lvfMatchId,
                code: $existing->code ?? $match->code,
                playedAt: $match->playedAt,
                homeClubId: $existing->homeClubId,
                homeName: $existing->homeName,
                awayClubId: $existing->awayClubId,
                awayName: $existing->awayName,
                homeSets: $match->homeSets ?? $existing->homeSets,
                awaySets: $match->awaySets ?? $existing->awaySets,
                location: $existing->location ?? $match->location,
                matchday: $existing->matchday ?? $match->matchday,
                phase: $existing->phase ?? $match->phase,
                competition: $existing->competition ?? $match->competition,
            );
        }

        return array_values($merged);
    }

    /**
     * @return bool true se la gara è stata creata, false se aggiornata
     */
    private function upsertMatch(Season $season, LvfMatch $match): bool
    {
        return DB::transaction(function () use ($season, $match) {
            $homeTeam = $this->resolveTeam($match->homeClubId, $match->homeName);
            $awayTeam = $this->resolveTeam($match->awayClubId, $match->awayName);

            $game = Game::where('lvf_match_id', $match->lvfMatchId)->first();
            $created = $game === null;

            $attributes = [
                'season_id' => $season->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'match_date' => $match->playedAt,
                'location' => $match->location,
                'competition_type' => $this->resolveCompetition($match->competition),
                'matchday' => $match->matchday,
                'phase' => $match->phase,
                'lvf_synced_at' => now(),
            ];

            if ($match->isPlayed()) {
                $attributes['home_score'] = $match->homeSets;
                $attributes['away_score'] = $match->awaySets;
                $attributes['status'] = GameStatus::Completed;
            } else {
                $attributes['home_score'] = null;
                $attributes['away_score'] = null;
                $attributes['status'] = GameStatus::Scheduled;
            }

            if ($created) {
                Game::create(['lvf_match_id' => $match->lvfMatchId] + $attributes);
            } else {
                $game->fill($attributes)->save();
            }

            return $created;
        });
    }

    /**
     * Toglie dall'archivio le gare importate che non compaiono più sulle
     * pagine della Lega.
     *
     * È il gemello della potatura già fatta sulla classifica: quando una gara
     * viene rinviata o annullata la Lega la toglie dal calendario, e senza
     * questo passaggio resterebbe pubblicata sul sito con data e avversaria
     * ormai false.
     *
     * Tre cautele, in ordine di gravità del danno che evitano:
     *
     * 1. Se il remoto non ha restituito nessuna gara non si cancella niente:
     *    una pagina in manutenzione svuoterebbe l'intera stagione.
     * 2. Si guardano solo le gare con `lvf_match_id`. Quelle inserite dal CMS
     *    non appartengono alla Lega e non si toccano mai.
     * 3. Una gara conclusa o con il tabellino già importato non si cancella:
     *    la cancellazione porterebbe con sé le statistiche individuali
     *    (`game_player_stats`), che sono l'unica copia di quel referto. Sparire
     *    dal calendario remoto è normale a fine stagione; perdere lo storico no.
     *    Il caso resta a log perché merita un occhio umano.
     *
     * @param  list<int>  $seenMatchIds
     */
    private function removeVanishedMatches(Season $season, array $seenMatchIds): int
    {
        if ($seenMatchIds === []) {
            return 0;
        }

        $vanished = Game::query()
            ->where('season_id', $season->id)
            ->whereNotNull('lvf_match_id')
            ->whereNotIn('lvf_match_id', $seenMatchIds)
            ->withCount('playerStats')
            ->get();

        $removed = 0;

        foreach ($vanished as $game) {
            if ($game->status === GameStatus::Completed || $game->player_stats_count > 0) {
                Log::warning(
                    "Sync Lega: la gara {$game->lvf_match_id} non è più sulle pagine della Lega ma ha "
                    .'un risultato o un tabellino: lasciata in archivio.'
                );

                continue;
            }

            $game->delete();
            $removed++;
        }

        return $removed;
    }

    /**
     * Trova o crea la squadra corrispondente a un club della Lega.
     *
     * La squadra del club esiste già come `is_internal`, inserita a mano: la si
     * riconosce dalla configurazione e le si aggancia l'identificativo remoto
     * invece di creare un doppione che spezzerebbe rose e statistiche.
     */
    private function resolveTeam(int $clubId, string $name): Team
    {
        // 1. Identificativo già noto: è la via normale a regime.
        $alias = TeamLvfClubId::with('team')->where('lvf_club_id', $clubId)->first();

        if ($alias?->team instanceof Team) {
            return $alias->team;
        }

        // 2. La squadra della società, riconosciuta dagli identificativi in
        //    configurazione (uno per stagione, la Lega li rinumera).
        $team = $this->resolveOwnTeam($clubId);

        // 3. Stessa denominazione di una squadra già in archivio: è la stessa
        //    società con l'identificativo nuovo della stagione.
        $team ??= Team::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->orderBy('id')->first();

        if ($team instanceof Team) {
            $this->attachClubId($team, $clubId, updateName: false);

            return $team;
        }

        // 4. Società mai vista: si crea.
        $team = Team::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'is_internal' => false,
        ]);

        $this->attachClubId($team, $clubId, updateName: false);

        return $team;
    }

    /**
     * La squadra del club esiste già in archivio con rose e statistiche
     * collegate: va agganciata, non duplicata.
     */
    private function resolveOwnTeam(int $clubId): ?Team
    {
        if (! in_array($clubId, $this->ownClubIds(), true)) {
            return null;
        }

        // La squadra della società ha un alias per ogni stagione già importata:
        // se ne ha uno fra i nostri, è lei, e va arricchita con l'identificativo
        // nuovo — non esclusa perché "già agganciata".
        $known = Team::where('is_internal', true)
            ->whereHas('lvfClubIds', fn ($query) => $query->whereIn('lvf_club_id', $this->ownClubIds()))
            ->orderBy('id')
            ->first();

        if ($known instanceof Team) {
            return $known;
        }

        // Primo aggancio in assoluto: si prende una squadra interna ancora
        // vergine. Una già collegata ad altri club non viene mai ripuntata.
        return Team::where('is_internal', true)
            ->whereDoesntHave('lvfClubIds')
            ->whereNull('lvf_club_id')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<int>
     */
    private function ownClubIds(): array
    {
        $configured = config('services.lvf.club_ids', []);

        return array_values(array_map('intval', array_filter((array) $configured)));
    }

    /**
     * Registra un identificativo di club per la squadra e allinea il logo.
     */
    private function attachClubId(Team $team, int $clubId, bool $updateName): void
    {
        TeamLvfClubId::firstOrCreate(
            ['lvf_club_id' => $clubId],
            ['team_id' => $team->id, 'season_year' => $this->seasonYear],
        );

        $team->forceFill([
            'lvf_club_id' => $clubId,
            'logo_url' => $this->logoUrl($clubId),
        ])->save();

        $this->syncLogo($team);
    }

    private function logoUrl(int $clubId): string
    {
        $base = rtrim((string) config('services.lvf.stats_base_url'), '/');

        return "{$base}/imgSocietaSquadra.aspx?idImg={$clubId}";
    }

    /**
     * Scarica il logo del club nella collezione riservata all'import.
     *
     * Il file finisce in `logo-lvf`, mai in `logo`: quest'ultima è la collezione
     * che il CMS usa per il logo caricato a mano, quindi una sincronizzazione
     * non può sovrascrivere una scelta fatta in redazione.
     *
     * Si riscarica solo se manca: il logo di un club cambia raramente e non vale
     * una richiesta a ogni giro.
     */
    private function syncLogo(Team $team): void
    {
        if ($team->lvf_club_id === null || $team->getFirstMedia(Team::LOGO_IMPORTED) !== null) {
            return;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('services.lvf.user_agent')])
                ->timeout((int) config('services.lvf.timeout', 30))
                ->get($this->logoUrl((int) $team->lvf_club_id));

            if (! $response->successful() || $response->body() === '') {
                return;
            }

            $team->addMediaFromString($response->body())
                ->usingFileName("club-{$team->lvf_club_id}.png")
                ->toMediaCollection(Team::LOGO_IMPORTED);
        } catch (Throwable $e) {
            // Un logo mancante non deve far fallire l'import della stagione.
            Log::warning("Logo del club {$team->lvf_club_id} non scaricato: {$e->getMessage()}");
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'squadra';
        $slug = $base;
        $suffix = 2;

        while (Team::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Vero se la gara appartiene a una delle divisioni che il sito segue.
     *
     * Il confronto è sulla stringa di competizione della Lega ("LVF A1 Fineco",
     * "LVF A2 …"), normalizzata togliendo spazi e maiuscole: "a1" intercetta
     * sia "A1" sia "Serie A1".
     *
     * Una competizione sconosciuta o assente viene tenuta: la pagina risultati
     * accoglie anche Coppa Italia e Champions, e scartare per un'etichetta non
     * riconosciuta significherebbe cancellare gare valide al giro di potatura.
     */
    private function belongsToTrackedDivision(?string $competition): bool
    {
        if ($competition === null || trim($competition) === '') {
            return true;
        }

        $normalized = preg_replace('/\s+/u', '', mb_strtolower($competition)) ?? '';

        $tracked = config('services.lvf.divisions', ['a1']);
        $excluded = config('services.lvf.excluded_divisions', ['a2', 'a3', 'b1', 'b2']);

        foreach ($tracked as $division) {
            if (str_contains($normalized, mb_strtolower((string) $division))) {
                return true;
            }
        }

        foreach ($excluded as $division) {
            if (str_contains($normalized, mb_strtolower((string) $division))) {
                return false;
            }
        }

        return true;
    }

    /**
     * La Lega non usa i nomi dell'enum interno: "LVF A1 Fineco" è il campionato.
     */
    private function resolveCompetition(?string $competition): CompetitionType
    {
        $normalized = mb_strtolower($competition ?? '');

        return match (true) {
            str_contains($normalized, 'coppa italia') => CompetitionType::CoppaItalia,
            str_contains($normalized, 'champions') => CompetitionType::ChampionsLeague,
            default => CompetitionType::Championship,
        };
    }

    /**
     * @param  list<LvfStandingRow>  $rows
     */
    private function upsertStandings(Season $season, array $rows): int
    {
        if ($rows === []) {
            Log::warning('Sync Lega: classifica vuota, le righe esistenti restano invariate.');

            return 0;
        }

        return DB::transaction(function () use ($season, $rows) {
            $competition = CompetitionType::Championship;
            $syncedTeamIds = [];

            foreach ($rows as $row) {
                $team = $this->resolveTeam($row->clubId, $row->teamName);
                $syncedTeamIds[] = $team->id;

                Standing::updateOrCreate(
                    [
                        'season_id' => $season->id,
                        'competition_type' => $competition,
                        'team_id' => $team->id,
                    ],
                    [
                        'position' => $row->position,
                        'points' => $row->points,
                        'played' => $row->played,
                        'won' => $row->won,
                        'lost' => $row->lost,
                        'won_3_0' => $row->won30,
                        'won_3_1' => $row->won31,
                        'won_3_2' => $row->won32,
                        'lost_2_3' => $row->lost23,
                        'lost_1_3' => $row->lost13,
                        'lost_0_3' => $row->lost03,
                        'sets_won' => $row->setsWon,
                        'sets_lost' => $row->setsLost,
                        'points_for' => $row->pointsFor,
                        'points_against' => $row->pointsAgainst,
                        'set_ratio' => $row->setRatio,
                        'point_ratio' => $row->pointRatio,
                        'synced_at' => now(),
                    ]
                );
            }

            // Una squadra esclusa dal campionato sparisce dalla classifica
            // remota: va rimossa anche qui, altrimenti resterebbe in pagina.
            Standing::where('season_id', $season->id)
                ->where('competition_type', $competition)
                ->whereNotIn('team_id', $syncedTeamIds)
                ->delete();

            return count($rows);
        });
    }

    /**
     * La stagione 2026/2027 è `stagione=2026` per la Lega.
     */
    private function resolveSeason(int $seasonYear): Season
    {
        $season = Season::where('lvf_season_year', $seasonYear)->first();

        if ($season instanceof Season) {
            return $season;
        }

        $name = sprintf('%d/%d', $seasonYear, $seasonYear + 1);

        // Se la stagione è già stata creata a mano dal CMS si riusa quella,
        // agganciandole l'anno della Lega. L'aggancio avviene solo per nome
        // esatto: una stagione con un nome diverso non è la stessa stagione.
        $season = Season::where('name', $name)->first();

        if ($season instanceof Season) {
            if ($season->lvf_season_year === null) {
                $season->forceFill(['lvf_season_year' => $seasonYear])->save();
            }

            return $season;
        }

        return Season::create([
            'name' => $name,
            'is_current' => ! Season::where('is_current', true)->exists(),
            'lvf_season_year' => $seasonYear,
        ]);
    }
}
