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
use App\Models\Post;
use App\Enums\GameStatus;
use App\Enums\PostStatus;
use Illuminate\Support\Facades\Cache;

class PublicController extends Controller
{
    public function home()
    {
        $data = Cache::remember('public:home', now()->addMinutes(5), function () {
            // Prossima partita programmata
            $nextGame = Game::with(['homeTeam', 'awayTeam'])
                ->where('status', GameStatus::Scheduled)
                ->where('match_date', '>=', now())
                ->orderBy('match_date')
                ->first();

            // Ultime 3 news pubblicate
            $latestNews = Post::where('status', PostStatus::Published)
                ->with('media')
                ->orderByDesc('published_at')
                ->take(3)
                ->get()
                ->map(fn ($post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt,
                    'published_at' => $post->published_at?->toISOString(),
                    'image_url' => $post->getFirstMediaUrl('post-images'),
                ]);

            return [
                'nextGame' => $nextGame,
                'latestNews' => $latestNews,
            ];
        });

        return Inertia::render('Public/Home', $data);
    }

    public function stagione()
    {
        return $this->stagioneForTeam('serie-a1', 'public:stagione');
    }

    public function stagioneB1()
    {
        return $this->stagioneForTeam('serie-b1', 'public:stagione:b1', 'Serie B1');
    }

    /**
     * Logica condivisa per il caricamento roster di un team specifico.
     */
    private function stagioneForTeam(string $teamSlug, string $cacheKey, ?string $teamLabel = null): \Inertia\Response
    {
        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($teamSlug) {
            $team = Team::where('slug', $teamSlug)->first();
            $currentSeason = Season::current()->first();

            $roster = [];
            $seasonName = null;

            if ($team && $currentSeason) {
                $roster = Roster::with([
                        'player',
                        'media',
                        'player.stats' => fn ($query) => $query->where('season_id', $currentSeason->id),
                    ])
                    ->where('team_id', $team->id)
                    ->where('season_id', $currentSeason->id)
                    ->orderBy('jersey_number')
                    ->get();

                $seasonName = $currentSeason->name;
            }

            return compact('roster', 'seasonName');
        });

        return Inertia::render('Public/Stagione', $teamLabel ? array_merge($data, ['teamLabel' => $teamLabel]) : $data);
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
