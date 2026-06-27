<?php

namespace App\Http\Controllers;


use Inertia\Inertia;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use App\Models\Game;
use App\Models\Player;
use App\Models\Sponsor;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class PublicController extends Controller
{
    public function home()
    {
        return Inertia::render('Public/Home');
    }

    public function stagione()
    {
        // Cache la query roster per 10 minuti (i dati cambiano raramente)
        $data = Cache::remember('public:stagione', now()->addMinutes(10), function () {
            $teamA1 = Team::where('slug', 'serie-a1')->first();
            $currentSeason = Season::current()->first();

            $roster = [];
            $seasonName = null;

            if ($teamA1 && $currentSeason) {
                $roster = Roster::with([
                        'player',
                        'player.stats' => fn ($query) => $query->where('season_id', $currentSeason->id),
                    ])
                    ->where('team_id', $teamA1->id)
                    ->where('season_id', $currentSeason->id)
                    ->orderBy('jersey_number')
                    ->get();

                $seasonName = $currentSeason->name;
            }

            return compact('roster', 'seasonName');
        });

        return Inertia::render('Public/Stagione', $data);
    }

    public function stagioneB1()
    {
        $data = Cache::remember('public:stagione:b1', now()->addMinutes(10), function () {
            $teamB1 = Team::where('slug', 'serie-b1')->first();
            $currentSeason = Season::current()->first();

            $roster = [];
            $seasonName = null;

            if ($teamB1 && $currentSeason) {
                $roster = Roster::with([
                        'player',
                        'player.stats' => fn ($query) => $query->where('season_id', $currentSeason->id),
                    ])
                    ->where('team_id', $teamB1->id)
                    ->where('season_id', $currentSeason->id)
                    ->orderBy('jersey_number')
                    ->get();

                $seasonName = $currentSeason->name;
            }

            return compact('roster', 'seasonName');
        });

        return Inertia::render('Public/Stagione', array_merge($data, ['teamLabel' => 'Serie B1']));
    }

    public function risultati()
    {
        $data = Cache::remember('public:risultati', now()->addMinutes(5), function () {
            $currentSeason = Season::current()->first();

            $games = [];

            if ($currentSeason) {
                $games = Game::with(['homeTeam', 'awayTeam'])
                    ->where('season_id', $currentSeason->id)
                    ->orderByDesc('match_date')
                    ->get();
            }

            return compact('games');
        });

        return Inertia::render('Public/Risultati', $data);
    }

    public function gallery()
    {
        // Le foto vengono gestite tramite Spatie Media Library.
        // Per ora rendiamo la pagina senza dati, il contenuto arriverà dal CMS.
        return Inertia::render('Public/Gallery', [
            'media' => [],
        ]);
    }

    public function staff()
    {
        $staff = Cache::remember('public:staff', now()->addMinutes(30), function () {
            return Player::where('is_staff', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->first_name . ' ' . $p->last_name,
                    'role' => $p->staff_role ?? 'Staff',
                    'photo_url' => $p->photo_url ?? $p->getFirstMediaUrl('player-photos'),
                ])
                ->toArray();
        });

        return Inertia::render('Public/Staff', [
            'staff' => $staff,
        ]);
    }

    public function sponsor()
    {
        $sponsors = Cache::remember('public:sponsor', now()->addMinutes(30), function () {
            return Sponsor::orderBy('tier')
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        });

        return Inertia::render('Public/Sponsor', [
            'sponsors' => $sponsors,
        ]);
    }

    public function shop()
    {
        $products = Cache::remember('public:shop', now()->addMinutes(10), function () {
            return Product::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        });

        return Inertia::render('Public/Shop', [
            'products' => $products,
        ]);
    }

    public function shopCheckout()
    {
        $cart = ['items' => [], 'total' => 0];

        return Inertia::render('Public/ShopCheckout', [
            'cart' => $cart,
        ]);
    }
}
