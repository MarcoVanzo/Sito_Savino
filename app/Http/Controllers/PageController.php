<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Enums\StaffType;
use App\Models\Page;
use App\Models\Roster;
use App\Models\Season;
use App\Models\StaffMember;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
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
        'Public/Societa',
        'Public/Roster',
        'Public/Shop',
        'Public/ShopCheckout',
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

    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('status', PostStatus::Published)
            ->first();

        if (! $page) {
            abort(404);
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
            'Public/Societa' => $this->getSocietaData(),
            'Public/Roster' => $this->getRosterData(),
            default => [],
        };
    }

    private function getSocietaData(): array
    {
        return [
            'dirigenza' => Cache::remember('public:organigramma', now()->addMinutes(30), function () {
                return StaffMember::where('type', StaffType::Dirigenza)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->full_name,
                        'role' => $p->role,
                        'photo_url' => $p->getFirstMediaUrl('staff'),
                    ])
                    ->toArray();
            }),
        ];
    }

    private function getRosterData(): array
    {
        return Cache::remember('public:roster_page', now()->addMinutes(10), function () {
            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            $players = [];

            if ($currentSeason) {
                $team = Team::where('category', 'A1')->first();

                if ($team) {
                    $players = Roster::with(['player', 'media'])
                        ->where('team_id', $team->id)
                        ->where('season_id', $currentSeason->id)
                        ->get()
                        ->map(fn ($r) => [
                            'id' => $r->player->id ?? $r->id,
                            'first_name' => $r->player->first_name ?? '',
                            'last_name' => $r->player->last_name ?? '',
                            'number' => $r->shirt_number ?? $r->player->shirt_number ?? null,
                            'role' => $r->player->role ?? null,
                            'photo_url' => $r->getFirstMediaUrl('roster') ?: ($r->player?->getFirstMediaUrl('players') ?: null),
                        ])
                        ->toArray();
                }
            }

            return [
                'players' => $players,
                'seasonName' => $currentSeason?->name ?? 'Stagione corrente',
            ];
        });
    }
}
