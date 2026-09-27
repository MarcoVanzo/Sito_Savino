<?php

namespace App\Services\Cev;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Team;
use App\Services\Cev\Data\CevMatch;
use App\Services\Cev\Data\CevStandingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Importa calendario, risultati e classifica del girone della CEV Champions
 * League dal portale della CEV.
 *
 * Valgono le stesse due invarianti del sync della Lega (§12 di CLAUDE.md):
 *
 * 1. È idempotente: ogni gara ha il suo `mID` sul portale, salvato in
 *    `games.cev_match_id`, e si fa upsert su quello.
 * 2. Non tocca il lavoro fatto a mano: le gare inserite dal pannello non hanno
 *    `cev_match_id` e restano come sono. Anche su quelle importate si scrivono
 *    solo le colonne che vengono dalla CEV: il link della diretta resta della
 *    redazione.
 *
 * Si pubblicano solo le gare con entrambe le squadre note e una data: "la
 * vincente del 3° turno" o una data non ancora fissata non sono informazioni
 * da mettere in calendario. Compaiono da sole al giro in cui la CEV le completa.
 */
class CevSyncService
{
    private ?Team $squadraDellaSocieta = null;

    public function __construct(
        private readonly CevClient $client,
        private readonly CevMatchParser $matchParser,
        private readonly CevStandingsParser $standingsParser,
    ) {}

    public static function make(): self
    {
        return new self(CevClient::fromConfig(), CevMatchParser::fromConfig(), new CevStandingsParser);
    }

    /**
     * @return array{matches_created: int, matches_updated: int, matches_removed: int, matches_skipped: int, teams_created: int, standings: int, girone: string|null, societa_trovata: bool}
     */
    public function sync(int $competitionId, int $seasonYear): array
    {
        $season = $this->resolveSeason($seasonYear);
        $this->squadraDellaSocieta = $this->resolveSquadraDellaSocieta();

        $stats = [
            'matches_created' => 0, 'matches_updated' => 0, 'matches_removed' => 0,
            'matches_skipped' => 0, 'teams_created' => 0, 'standings' => 0, 'girone' => null,
            'societa_trovata' => false,
        ];

        $teamsBefore = Team::count();
        $pagina = $this->client->competition($competitionId);
        $this->verificaEdizione($pagina, $competitionId, $seasonYear);
        $phaseIds = $this->matchParser->phaseIds($pagina, $competitionId);

        if ($phaseIds === []) {
            throw new CevException("La competizione {$competitionId} non elenca nessuna fase.");
        }

        $seen = [];
        $fasiConGironi = [];
        $fasiVuote = [];
        $garePubblicate = 0;
        $garaDellaSocieta = false;

        foreach ($phaseIds as $phaseId) {
            $gare = $this->matchParser->parse($this->client->matches($competitionId, $phaseId));
            $garePubblicate += count($gare);

            // Ogni fase del portale elenca le sue gare fin dal sorteggio, anche
            // solo con i segnaposto ("Winner CLVW 79/80"): una pagina senza
            // gare è una pagina rotta, non una fase vuota.
            if ($gare === []) {
                $fasiVuote[] = $phaseId;
            }

            foreach ($gare as $match) {
                if ($match->matchday !== null) {
                    $fasiConGironi[$phaseId] = true;
                }

                if ($this->eLaNostraSquadra($match->homeName) || $this->eLaNostraSquadra($match->awayName)) {
                    $garaDellaSocieta = true;
                }

                if (! $this->daPubblicare($match)) {
                    $stats['matches_skipped']++;

                    continue;
                }

                $created = $this->upsertMatch($season, $match);
                $seen[] = $match->cevMatchId;
                $stats[$created ? 'matches_created' : 'matches_updated']++;
            }
        }

        // Il markup è cambiato e il parser non riconosce più niente: meglio un
        // errore nei log che una pagina che smette di aggiornarsi in silenzio.
        if ($garePubblicate === 0) {
            throw new CevException('Nessuna gara letta dalle '.count($phaseIds)." fasi della competizione {$competitionId}: il portale ha cambiato le pagine?");
        }

        // Con una fase illeggibile non si toglie niente: le sue gare
        // risulterebbero "sparite" e verrebbero cancellate, link della diretta
        // compresi.
        if ($fasiVuote === []) {
            $stats['matches_removed'] = $this->removeVanishedMatches($season, $seen);
        } else {
            Log::error('Sync CEV: fasi senza gare ('.implode(', ', $fasiVuote).'), nessuna gara rimossa in questo giro.');
        }

        // Il nome con cui la CEV scrive la società può cambiare (un nuovo
        // sponsor): senza questo controllo le nostre gare finirebbero su una
        // squadra "avversaria" e la pagina perderebbe evidenza e classifica.
        if (! $garaDellaSocieta) {
            Log::error('Sync CEV: nessuna gara della società nella competizione '.$competitionId
                .'. Il nome in services.cev.nomi_della_societa corrisponde ancora a quello della CEV?');
        }

        $stats['societa_trovata'] = $garaDellaSocieta;

        foreach (array_keys($fasiConGironi) as $phaseId) {
            $righe = $this->righeDelNostroGirone(
                $this->standingsParser->parse($this->client->standings($competitionId, $phaseId))
            );

            if ($righe !== []) {
                $stats['standings'] = $this->upsertStandings($season, $righe);
                $stats['girone'] = $righe[0]->girone;
            }
        }

        $stats['teams_created'] = Team::count() - $teamsBefore;

        return $stats;
    }

    /**
     * L'edizione giusta: la femminile, e dell'anno della stagione (la
     * Champions 2026/27 è "Volley 2027"). Un ID sbagliato di una cifra in
     * configurazione importerebbe la maschile, o l'edizione passata dentro la
     * stagione di oggi.
     */
    private function verificaEdizione(string $pagina, int $competitionId, int $seasonYear): void
    {
        $edizione = $this->matchParser->edizione($pagina);

        if ($edizione['anno'] === null) {
            throw new CevException("La pagina della competizione {$competitionId} non dice di che edizione si tratta.");
        }

        if (! $edizione['femminile']) {
            throw new CevException("La competizione {$competitionId} non è la Champions League femminile: controllare services.cev.competition_id.");
        }

        if ($edizione['anno'] !== $seasonYear + 1) {
            throw new CevException("La competizione {$competitionId} è l'edizione {$edizione['anno']}, non quella della stagione {$seasonYear}/".($seasonYear + 1).': aggiornare services.cev.');
        }
    }

    /**
     * Una gara va in calendario se ha una data e due squadre note. Fa
     * eccezione quella della società con l'avversaria ancora da decidere
     * ("la vincente del 3° turno"): è una nostra partita in casa con data e
     * ora, e i tifosi devono poterla vedere. L'avversaria resta "da definire"
     * finché la CEV non la pubblica.
     */
    private function daPubblicare(CevMatch $match): bool
    {
        if ($match->playedAt === null) {
            return false;
        }

        if ($match->hasTeams()) {
            return true;
        }

        return ($this->eLaNostraSquadra($match->homeName) && $match->awayTeamId === 0)
            || ($this->eLaNostraSquadra($match->awayName) && $match->homeTeamId === 0);
    }

    private function squadraDellaGara(int $teamId, string $name): Team
    {
        if ($teamId === 0 && ! $this->eLaNostraSquadra($name)) {
            return Team::firstOrCreate(
                ['slug' => Team::SLUG_DA_DEFINIRE],
                ['name' => 'Avversaria da definire', 'is_internal' => false],
            );
        }

        return $this->resolveTeam($name);
    }

    private function upsertMatch(Season $season, CevMatch $match): bool
    {
        return DB::transaction(function () use ($season, $match) {
            $attributes = [
                'season_id' => $season->id,
                'home_team_id' => $this->squadraDellaGara($match->homeTeamId, $match->homeName)->id,
                'away_team_id' => $this->squadraDellaGara($match->awayTeamId, $match->awayName)->id,
                'match_date' => $match->playedAt,
                'location' => $match->location,
                'competition_type' => CompetitionType::ChampionsLeague,
                'matchday' => $match->matchday,
                'phase' => $match->phase,
            ];

            if ($match->isPlayed()) {
                $attributes += ['home_score' => $match->homeSets, 'away_score' => $match->awaySets, 'status' => GameStatus::Completed];
            } else {
                $attributes += ['home_score' => null, 'away_score' => null, 'status' => GameStatus::Scheduled];
            }

            $game = Game::where('cev_match_id', $match->cevMatchId)->first();

            if ($game === null) {
                Game::create(['cev_match_id' => $match->cevMatchId] + $attributes);

                return true;
            }

            $game->fill($attributes)->save();

            return false;
        });
    }

    /**
     * Toglie le gare importate che il portale non pubblica più (o non più con
     * squadre e data certe). Con le stesse cautele del sync della Lega: se il
     * portale non ha restituito nessuna gara non si cancella niente, le gare
     * inserite a mano non si guardano, e una gara già giocata resta in
     * archivio perché il risultato è storia.
     *
     * @param  list<int>  $seen
     */
    private function removeVanishedMatches(Season $season, array $seen): int
    {
        if ($seen === []) {
            return 0;
        }

        $removed = 0;

        $vanished = Game::query()
            ->where('season_id', $season->id)
            ->whereNotNull('cev_match_id')
            ->whereNotIn('cev_match_id', $seen)
            ->get();

        foreach ($vanished as $game) {
            if ($game->status === GameStatus::Completed) {
                Log::warning("Sync CEV: la gara {$game->cev_match_id} non è più sul portale ma ha un risultato: lasciata in archivio.");

                continue;
            }

            $game->delete();
            $removed++;
        }

        return $removed;
    }

    /**
     * In Champions interessa la classifica del girone della società: le altre
     * decidono solo chi incontreremo dopo, e mescolarle in un'unica tabella
     * darebbe posizioni ripetute.
     *
     * @param  list<CevStandingRow>  $righe
     * @return list<CevStandingRow>
     */
    private function righeDelNostroGirone(array $righe): array
    {
        $girone = null;

        foreach ($righe as $riga) {
            if ($this->eLaNostraSquadra($riga->teamName)) {
                $girone = $riga->girone;

                break;
            }
        }

        return $girone === null
            ? []
            : array_values(array_filter($righe, fn (CevStandingRow $riga) => $riga->girone === $girone));
    }

    /**
     * @param  list<CevStandingRow>  $righe
     */
    private function upsertStandings(Season $season, array $righe): int
    {
        return DB::transaction(function () use ($season, $righe) {
            $teamIds = [];

            foreach ($righe as $riga) {
                $team = $this->resolveTeam($riga->teamName);
                $teamIds[] = $team->id;

                Standing::updateOrCreate(
                    ['season_id' => $season->id, 'competition_type' => CompetitionType::ChampionsLeague, 'team_id' => $team->id],
                    [
                        'girone' => $riga->girone,
                        'position' => $riga->position,
                        'points' => $riga->points,
                        'played' => $riga->played,
                        'won' => $riga->won,
                        'lost' => $riga->lost,
                        'won_3_0' => $riga->won30,
                        'won_3_1' => $riga->won31,
                        'won_3_2' => $riga->won32,
                        'lost_2_3' => $riga->lost23,
                        'lost_1_3' => $riga->lost13,
                        'lost_0_3' => $riga->lost03,
                        'sets_won' => $riga->setsWon,
                        'sets_lost' => $riga->setsLost,
                        'points_for' => $riga->pointsFor,
                        'points_against' => $riga->pointsAgainst,
                        'set_ratio' => $riga->setRatio,
                        'point_ratio' => $riga->pointRatio,
                        'synced_at' => now(),
                    ]
                );
            }

            // Una squadra che non è più nel girone (una vincente del turno
            // preliminare sostituita, un ritiro) non deve restare in tabella.
            Standing::where('season_id', $season->id)
                ->where('competition_type', CompetitionType::ChampionsLeague)
                ->whereNotIn('team_id', $teamIds)
                ->delete();

            return count($righe);
        });
    }

    /**
     * La nostra squadra si riconosce dal nome che la CEV le dà (la `TeamID` del
     * portale cambia a ogni edizione) e diventa la squadra di A1 già in
     * archivio: rose e statistiche restano collegate. Le avversarie si cercano
     * per nome fra quelle importate e, se mancano, si creano.
     */
    private function resolveTeam(string $name): Team
    {
        if ($this->eLaNostraSquadra($name) && $this->squadraDellaSocieta instanceof Team) {
            return $this->squadraDellaSocieta;
        }

        $team = Team::where('is_internal', false)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->orderBy('id')
            ->first();

        return $team ?? $this->squadraDellaLegaDellaStessaCitta($name) ?? Team::create([
            'name' => trim($name),
            'slug' => $this->uniqueSlug($name),
            'is_internal' => false,
        ]);
    }

    /**
     * Le italiane in Champions sono le stesse squadre del campionato, ma la CEV
     * le scrive col suo nome ("A. Carraro Prosecco DOC CONEGLIANO" contro
     * "Prosecco Doc A.Carraro Imoco Conegliano" della Lega): senza questo
     * passaggio si creerebbe un doppione senza logo. Il confronto è sulla
     * città, l'ultima parola in maiuscolo, e vale solo fra le squadre della
     * Lega e solo se una sola ci corrisponde: due squadre CEV di Istanbul non
     * devono diventare la stessa.
     */
    private function squadraDellaLegaDellaStessaCitta(string $name): ?Team
    {
        if (preg_match('/\s(\p{Lu}[\p{Lu}\-\']{2,})$/u', trim($name), $m) !== 1) {
            return null;
        }

        $citta = mb_strtolower($m[1]);

        $candidate = Team::where('is_internal', false)
            ->whereNotNull('lvf_club_id')
            ->get()
            ->filter(fn (Team $team) => preg_match('/\b'.preg_quote($citta, '/').'\b/u', mb_strtolower($team->name)) === 1);

        return $candidate->count() === 1 ? $candidate->first() : null;
    }

    private function eLaNostraSquadra(string $name): bool
    {
        $name = mb_strtolower(trim($name));

        foreach ((array) config('services.cev.nomi_della_societa', []) as $nostro) {
            if ($name === mb_strtolower(trim((string) $nostro))) {
                return true;
            }
        }

        return false;
    }

    /**
     * La prima squadra della società: quella agganciata agli identificativi di
     * club della Lega, cioè la squadra di A1 con rose e statistiche.
     */
    private function resolveSquadraDellaSocieta(): Team
    {
        $clubIds = array_values(array_map('intval', array_filter((array) config('services.lvf.club_ids', []))));

        $team = Team::where('is_internal', true)
            ->whereHas('lvfClubIds', fn ($query) => $query->whereIn('lvf_club_id', $clubIds))
            ->orderBy('id')
            ->first();

        $team ??= Team::where('is_internal', true)
            ->where(fn ($query) => $query->whereNull('category')->orWhereNotIn('category', Team::CATEGORIE_VIVAIO))
            ->orderBy('id')
            ->first();

        if (! $team instanceof Team) {
            throw new CevException('Nessuna squadra della società in archivio: la sincronizzazione della Lega va fatta prima.');
        }

        return $team;
    }

    /**
     * La stagione è quella della Lega: si cerca per anno di apertura, poi per
     * nome. Non se ne crea una: la crea il sync del campionato, che parte
     * sempre prima della Champions.
     */
    private function resolveSeason(int $seasonYear): Season
    {
        $season = Season::where('lvf_season_year', $seasonYear)->first()
            ?? Season::where('name', sprintf('%d/%d', $seasonYear, $seasonYear + 1))->first();

        if (! $season instanceof Season) {
            throw new CevException("Stagione {$seasonYear}/".($seasonYear + 1).' non in archivio: va prima sincronizzato il campionato.');
        }

        return $season;
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
}
