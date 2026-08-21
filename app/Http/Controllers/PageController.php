<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Enums\StaffType;
use App\Models\Game;
use App\Models\Page;
use App\Models\Roster;
use App\Models\Season;
use App\Models\StaffMember;
use App\Models\Team;
use App\Services\SponsorDirectory;
use App\Support\CmsFile;
use App\Support\LiveStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PageController extends Controller
{
    /**
     * Template consentiti per il rendering Inertia.
     * Previene code injection da valori malevoli nel database.
     * Deve corrispondere ai componenti Vue in resources/js/Pages/Public/.
     */
    private const ALLOWED_TEMPLATES = [
        'Public/ContentPage',
        'Public/Stagione',
        'Public/Home',
        'Public/Societa/Organigramma',
        'Public/Societa/Storia',
        'Public/Societa/Palazzetto',
        'Public/Societa/Safeguarding',
        'Public/Roster',

        'Public/Ticketing',
        'Public/Sponsor',
        'Public/Youth',
        'Public/SummerCamp',
        'Public/TalentDay',
        'Public/Sociale',
        'Public/Comunicazione',
        'Public/Risultati',
        'Public/Gallery',
        'Public/Staff',
        'Public/Contatti',
    ];

    /**
     * Mappatura dei singoli slug di pagina alle rispettive sezioni (per URL canonici SEO).
     */
    private const SLUG_SECTION_MAP = [
        // Società
        'organigramma' => 'societa',
        'storia' => 'societa',
        'safeguarding' => 'societa',
        'palazzetto' => 'societa',

        // Ticketing
        'abbonamenti' => 'ticketing',
        'biglietteria' => 'ticketing',
        'convenzioni' => 'ticketing',
        'accessibilita' => 'ticketing',

        // Sponsor
        'diventa-sponsor' => 'sponsor',
        'title-sponsor' => 'sponsor',
        'hospitality' => 'sponsor',

        // Youth
        'settore-giovanile' => 'youth',
        'talent-day' => 'youth',
        'affiliazioni' => 'youth',

        // Summer Camp
        'summer-camp' => 'summer-camp',
        'iscrizione-experience' => 'summer-camp',

        // Sociale
        'volley-4-all' => 'sociale',
        'progetti-sociali' => 'sociale',
        'sostenibilita' => 'sociale',
        'progetto-scuola' => 'sociale',

        // Comunicazione
        'accrediti-stampa' => 'comunicazione',
        'cartelle-stampa' => 'comunicazione',
        'double-face' => 'comunicazione',
        'magazine' => 'comunicazione',
    ];

    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('status', PostStatus::Published)
            ->with('media')
            ->first();

        if (! $page) {
            abort(404);
        }

        // Espone ai template pubblici la copertina (hero) e le foto della
        // galleria di pagina, entrambe gestite dal pannello.
        $page->append(['cover_url', 'gallery_images']);

        // Evita contenuti duplicati (SEO): se l'utente accede alla pagina tramite la rotta generica
        // o di sezione (es. /societa/contatti), reindirizza con un redirect 301 permanente
        // alla rotta canonical principale (/contatti o /en/contacts).
        if ($page->slug === 'contatti') {
            $routePrefix = app()->getLocale() === 'it' ? '' : app()->getLocale().'.';

            return redirect()->route($routePrefix.'contatti', [], 301);
        }

        // Evita contenuti duplicati (SEO): se la pagina appartiene a una sezione specifica
        // ma è stata chiamata tramite la rotta catch-all, fai un redirect 301 alla rotta corretta.
        if (request()->routeIs('*pages.show') && isset(self::SLUG_SECTION_MAP[$page->slug])) {
            $section = self::SLUG_SECTION_MAP[$page->slug];
            $routePrefix = app()->getLocale() === 'it' ? '' : app()->getLocale().'.';

            // Quando lo slug coincide con la sezione l'URL canonico è la sezione
            // e basta: la regola generale produceva /summer-camp/summer-camp.
            if ($page->slug === $section && Route::has($routePrefix.$section)) {
                return redirect()->route($routePrefix.$section, [], 301);
            }

            return redirect()->route($routePrefix.$section.'.page', ['slug' => $page->slug], 301);
        }

        // Se il template è nella whitelist, usalo. Altrimenti renderizza
        // la pagina generica con un layout che mostra il contenuto della page.
        $template = $page->template && in_array($page->template, self::ALLOWED_TEMPLATES)
            ? $page->template
            : 'Public/ContentPage'; // Fallback generico che renderizza il contenuto

        // Props aggiuntive per template specifici
        $extra = $this->getTemplateData($template);

        // I file caricati dal pannello dentro `content_data` (press kit,
        // magazine, immagini dei pulsanti) diventano indirizzi pubblici veri:
        // i template li usavano come "/storage/{percorso}", che in produzione
        // — con i file su Spaces — non porta da nessuna parte.
        $dati = $page->toArray();
        $dati['content_data'] = is_array($dati['content_data'] ?? null)
            ? CmsFile::resolveInContentData($dati['content_data'])
            : $dati['content_data'];

        // Il video di coda passa dallo stesso filtro della diretta delle gare:
        // un indirizzo fuori dalle piattaforme conosciute non viene incorporato
        // nella pagina con i permessi del sito.
        if (is_array($dati['content_data'] ?? null) && isset($dati['content_data']['video_url'])) {
            $dati['content_data']['video_embed_url'] = LiveStream::embedUrl($dati['content_data']['video_url']);
            $dati['content_data']['video_url'] = LiveStream::externalUrl($dati['content_data']['video_url']);
        }

        return Inertia::render($template, array_merge([
            'page' => $dati,
        ], $extra));
    }

    /**
     * Carica dati aggiuntivi in base al template.
     * Ogni template specializzato riceve le props che il componente Vue si aspetta.
     */
    private function getTemplateData(string $template): array
    {
        return match ($template) {
            'Public/Societa/Organigramma' => $this->getSocietaData(),
            'Public/Comunicazione' => $this->getComunicazioneData(),
            'Public/Roster' => $this->getRosterData(),
            'Public/Sponsor' => $this->getSponsorData(),
            default => [],
        };
    }

    /**
     * Le prossime gare in casa, per la tendina del modulo accrediti.
     *
     * La gara si scriveva a mano e arrivavano richieste per partite che non
     * esistono o scritte in dieci modi diversi. Sono le prossime due in
     * calendario in cui la squadra di casa e' una squadra della societa': le
     * trasferte non si accreditano qui.
     *
     * @return array{upcomingHomeGames: list<array{value: string, label: string}>}
     */
    private function getComunicazioneData(): array
    {
        $locale = app()->getLocale();

        return [
            'upcomingHomeGames' => Cache::remember("public:accrediti:gare:{$locale}", now()->addMinutes(30), function () {
                return Game::with(['homeTeam', 'awayTeam'])
                    ->where('status', GameStatus::Scheduled)
                    ->where('match_date', '>=', now())
                    ->whereHas('homeTeam', fn ($team) => $team->where('is_internal', true))
                    ->orderBy('match_date')
                    ->take(2)
                    ->get()
                    ->map(function (Game $gara) {
                        // Le due squadre e la data ci sono per costruzione: la
                        // query filtra su una squadra di casa interna e su una
                        // data futura.
                        $sfida = $gara->homeTeam->name.' — '.$gara->awayTeam->name;

                        return [
                            'value' => $sfida,
                            'label' => $sfida.' · '.$gara->match_date->translatedFormat('j F Y'),
                        ];
                    })
                    ->values()
                    ->all();
            }),
        ];
    }

    private function getSocietaData(): array
    {
        $locale = app()->getLocale();

        return [
            'dirigenza' => Cache::remember("public:organigramma:page:{$locale}", now()->addMinutes(30), function () {
                return StaffMember::with('media')
                    ->where('type', StaffType::Dirigenza)
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
            }),
        ];
    }

    private function getRosterData(): array
    {
        $locale = app()->getLocale();

        return Cache::remember("public:roster_page:{$locale}", now()->addMinutes(10), function () {
            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            $players = [];

            if ($currentSeason) {
                $team = Team::where('category', 'A1')->first();

                if ($team) {
                    $players = Roster::with(['player', 'media'])
                        ->whereHas('player')
                        ->where('team_id', $team->id)
                        ->where('season_id', $currentSeason->id)
                        ->orderByRaw('jersey_number IS NULL, jersey_number')
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($r) => [
                            'id' => $r->player->id ?? $r->id,
                            'first_name' => $r->player->first_name ?? '',
                            'last_name' => $r->player->last_name ?? '',
                            'number' => $r->jersey_number,
                            'role' => $r->role?->value,
                            'photo_url' => $r->getFirstMediaUrl('rosters_official', 'card') ?: ($r->player?->getFirstMediaUrl('players', 'card') ?: $r->player?->getFirstMediaUrl('players') ?: null),
                        ])
                        ->toArray();
                }
            }

            return [
                'players' => $players,
                'seasonName' => $currentSeason->name ?? __('Stagione corrente'),
            ];
        });
    }

    private function getSponsorData(): array
    {
        return [
            'tiers' => app(SponsorDirectory::class)->tiers(),
        ];
    }
}
