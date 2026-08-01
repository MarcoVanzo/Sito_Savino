<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Enums\StaffType;
use App\Models\Page;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\StaffMember;
use App\Models\Team;
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

        // Espone l'URL della copertina ai template pubblici (usata come hero).
        $page->append('cover_url');

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

        return Inertia::render($template, array_merge([
            'page' => $page,
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
            'Public/Roster' => $this->getRosterData(),
            'Public/Sponsor' => $this->getSponsorData(),
            default => [],
        };
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
        $locale = app()->getLocale();

        return [
            'sponsors' => Cache::remember("public:sponsor:{$locale}", now()->addMinutes(30), function () {
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
            }),
        ];
    }
}
