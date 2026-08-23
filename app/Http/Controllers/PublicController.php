<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\StaffType;
use App\Http\Controllers\Concerns\PresentaLeSquadre;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Roster;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\StaffMember;
use App\Models\Team;
use App\Services\PalmaresPresenter;
use App\Services\SponsorDirectory;
use App\Support\LiveStream;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    use PresentaLeSquadre;

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
                // Diretta: incorporabile solo dalle piattaforme conosciute, e
                // comunque solo se è un link web — il template lo usa come
                // `href` quando non si può incorporare.
                $nextGame['stream_url'] = LiveStream::externalUrl($nextGameModel->stream_url);
                $nextGame['stream_embed_url'] = LiveStream::embedUrl($nextGameModel->stream_url);
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
     * Stessa pagina di `/stagione`, con il banner di un'atleta già aperto.
     *
     * Il banner è una finestra sopra la rosa, non una pagina a sé: qui non si
     * costruisce niente di diverso, si dice solo quale atleta mostrare. Serve
     * perché un palmarès è qualcosa che si manda a qualcuno — senza un
     * indirizzo proprio non sarebbe né condivisibile né raggiungibile dal
     * tasto indietro.
     */
    public function stagioneAtleta(string $slug): Response
    {
        $locale = app()->getLocale();

        return $this->stagioneForTeam(
            'savino-del-bene-volley',
            "public:stagione:{$locale}",
            openPlayerSlug: $slug,
        );
    }

    /**
     * Logica condivisa per il caricamento roster di un team specifico.
     */
    private function stagioneForTeam(
        string $teamSlug,
        string $cacheKey,
        ?string $teamLabel = null,
        ?string $openPlayerSlug = null,
    ): Response {
        // Il palmarès si pubblica solo sulla prima squadra: le voci di
        // Wikipedia delle giovanili non esistono, e una card cliccabile che
        // apre una finestra vuota è peggio di una card che non si clicca.
        $withPalmares = $teamSlug === 'savino-del-bene-volley';

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($teamSlug, $withPalmares) {
            $team = Team::with('media')
                ->where('slug', $teamSlug)
                ->orWhere('category', $teamSlug === 'savino-del-bene-volley' ? 'A1' : 'B1')
                ->first();

            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            // Staff tecnico e medico filtrati per sezione (A1 vs Youth)
            $section = $teamSlug === 'savino-del-bene-volley' ? 'a1' : 'youth';

            return [
                ...$this->rosaDellaSquadra($team, $currentSeason, $withPalmares),
                'staffTecnico' => $this->staffDellaSezione(StaffType::Tecnico, $section),
                'staffMedico' => $this->staffDellaSezione(StaffType::Medico, $section),
            ];
        });

        if ($teamLabel !== null) {
            $data['teamLabel'] = $teamLabel;
        }

        // Distingue "nessun palmarès in archivio" da "su questa pagina il
        // palmarès non si pubblica": senza, le card delle giovanili sarebbero
        // cliccabili per aprire una finestra che non ha niente da dire.
        $data['palmaresEnabled'] = $withPalmares;
        $data['openPlayer'] = $openPlayerSlug;

        return Inertia::render('Public/Stagione', $data);
    }

    /**
     * Rosa, statistiche di stagione e intestazione della squadra.
     *
     * @return array<string, mixed>
     */
    private function rosaDellaSquadra(?Team $team, ?Season $currentSeason, bool $withPalmares): array
    {
        if (! $team || ! $currentSeason) {
            return ['roster' => [], 'seasonName' => null, 'seasonStats' => [], 'teamInfo' => null];
        }

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
            // Le righe nascoste dalla redazione restano in archivio per
            // non farle riapparire alla prossima importazione: qui non
            // devono nemmeno arrivare.
            'player.honours' => fn ($query) => $query->visible(),
        ])
            ->whereHas('player')
            ->where('team_id', $team->id)
            ->where('season_id', $currentSeason->id)
            ->orderByRaw('jersey_number IS NULL, jersey_number')
            ->orderBy('id')
            ->get();

        return [
            'roster' => $this->presentRoster($rosterEntries, $withPalmares),
            'seasonName' => $currentSeason->name,
            'seasonStats' => $this->presentSeasonStats($rosterEntries, $team->id),
            'teamInfo' => [
                'name' => $team->name,
                'logo' => $this->teamLogo($team),
            ],
        ];
    }

    /**
     * Staff di una sezione. Le righe senza sezione contano come prima squadra:
     * sono quelle inserite prima che il campo esistesse.
     *
     * @return array<int, array<string, mixed>>
     */
    private function staffDellaSezione(StaffType $type, string $section): array
    {
        return StaffMember::with('media')
            ->where('type', $type)
            ->where(function ($q) use ($section) {
                if ($section === 'a1') {
                    $q->where('section', 'a1')->orWhereNull('section');
                } else {
                    $q->where('section', $section);
                }
            })
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
    }

    /**
     * Rosa pronta per la pagina: la riga di roster così com'è, più lo slug
     * dell'atleta e — sulla prima squadra — il palmarès già aggregato.
     *
     * @param  Collection<int, Roster>  $rosterEntries
     * @return list<array<string, mixed>>
     */
    private function presentRoster(Collection $rosterEntries, bool $withPalmares): array
    {
        $presenter = $withPalmares ? app(PalmaresPresenter::class) : null;

        return $rosterEntries->map(function (Roster $entry) use ($presenter): array {
            $row = $entry->toArray();
            $player = $entry->player;

            $row['playerSlug'] = $player instanceof Player
                ? $player->id.'-'.Str::slug($player->full_name)
                : null;

            $row['palmares'] = $presenter !== null && $player instanceof Player
                ? $presenter->forPlayer($player)
                : null;

            return $row;
        })->all();
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

    public function sponsor(SponsorDirectory $sponsors)
    {
        $page = Page::where('slug', 'sponsor')->published()->first();

        return Inertia::render('Public/Sponsor', [
            'tiers' => $sponsors->tiers(),
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
