<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Enums\StaffType;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\StaffMember;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
                ->first()?->toArray();

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
                ])->toArray();

            $heroSlides = HeroSlide::active()->ordered()->with('media')->get()
                ->map(fn ($slide) => [
                    'id' => $slide->id,
                    'title' => $slide->title,
                    'subtitle' => $slide->subtitle,
                    'image' => $slide->getFirstMediaUrl('hero-slides') ?: '/images/hero1.jpg',
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
        return $this->stagioneForTeam('savino-del-bene-volley', 'public:stagione');
    }

    public function stagioneB1()
    {
        return $this->stagioneForTeam('serie-b1', 'public:stagione:b1', 'Serie B1');
    }

    /**
     * Logica condivisa per il caricamento roster di un team specifico.
     */
    private function stagioneForTeam(string $teamSlug, string $cacheKey, ?string $teamLabel = null): Response
    {
        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($teamSlug) {
            $team = Team::where('slug', $teamSlug)
                ->orWhere('category', $teamSlug === 'savino-del-bene-volley' ? 'A1' : 'B1')
                ->first();

            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

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
                    ->get()
                    ->toArray();

                $seasonName = $currentSeason->name;
            }

            // Staff tecnico e medico
            $mapStaff = fn ($p) => [
                'id' => $p->id,
                'name' => $p->full_name,
                'role' => $p->role,
                'photo_url' => $p->getFirstMediaUrl('staff'),
            ];

            $staffTecnico = StaffMember::with('media')
                ->where('type', StaffType::Tecnico)
                ->orderBy('sort_order')->get()->map($mapStaff)->toArray();

            $staffMedico = StaffMember::with('media')
                ->where('type', StaffType::Medico)
                ->orderBy('sort_order')->get()->map($mapStaff)->toArray();

            return compact('roster', 'seasonName', 'staffTecnico', 'staffMedico');
        });

        return Inertia::render('Public/Stagione', $teamLabel ? array_merge($data, ['teamLabel' => $teamLabel]) : $data);
    }

    public function risultati()
    {
        $data = Cache::remember('public:risultati', now()->addMinutes(5), function () {
            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            $games = [];

            if ($currentSeason) {
                $games = Game::with(['homeTeam', 'awayTeam'])
                    ->where('season_id', $currentSeason->id)
                    ->get()
                    ->toArray();
            }

            return compact('games');
        });

        return Inertia::render('Public/Risultati', $data);
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
        $page = Cache::remember('public:page:gallery', now()->addMinutes(30), function () {
            return Page::where('slug', 'gallery')->first();
        });

        $cacheKey = $playerFilter ? 'public:gallery_images:player_'.$playerFilter->id : 'public:gallery_images';

        $media = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($playerFilter) {
            $query = GalleryImage::active()->ordered();

            if ($playerFilter) {
                $query->whereHas('players', function ($q) use ($playerFilter) {
                    $q->where('players.id', $playerFilter->id);
                });
            }

            return $query->get()
                ->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->getFirstMediaUrl('gallery'),
                    'alt' => $img->title ?? 'Immagine Galleria',
                    'category' => $img->category ?? 'Partite',
                ])
                ->toArray();
        });

        // Get all players that have at least one gallery image for the filter dropdown
        $athletes = Cache::remember('public:gallery_athletes', now()->addMinutes(30), function () {
            return Player::whereHas('galleryImages')->get()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->full_name,
                'slug' => $p->id.'-'.Str::slug($p->full_name),
            ])->toArray();
        });

        return Inertia::render('Public/Gallery', [
            'page' => $page,
            'media' => $media,
            'athletes' => $athletes,
            'currentAthlete' => $playerFilter ? [
                'id' => $playerFilter->id,
                'name' => $playerFilter->full_name,
                'slug' => $playerFilter->id.'-'.Str::slug($playerFilter->full_name),
            ] : null,
        ]);
    }

    public function staff()
    {
        $staffTecnico = Cache::remember('public:staff_tecnico', now()->addMinutes(30), function () {
            return StaffMember::with('media')
                ->where('type', StaffType::Tecnico)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'role' => $p->role,
                    'photo_url' => $p->getFirstMediaUrl('staff'),
                ])
                ->toArray();
        });

        $staffMedico = Cache::remember('public:staff_medico', now()->addMinutes(30), function () {
            return StaffMember::with('media')
                ->where('type', StaffType::Medico)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'role' => $p->role,
                    'photo_url' => $p->getFirstMediaUrl('staff'),
                ])
                ->toArray();
        });

        return Inertia::render('Public/Staff', [
            'staffTecnico' => $staffTecnico,
            'staffMedico' => $staffMedico,
        ]);
    }

    public function organigramma()
    {
        $dirigenza = Cache::remember('public:organigramma', now()->addMinutes(30), function () {
            return StaffMember::with('media')
                ->where('type', StaffType::Dirigenza)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'role' => $p->role,
                    'photo_url' => $p->getFirstMediaUrl('staff'),
                ])
                ->toArray();
        });

        $page = Page::where('slug', 'organigramma')->first();

        return Inertia::render('Public/Organigramma', [
            'dirigenza' => $dirigenza,
            'page' => $page,
        ]);
    }

    public function sponsor()
    {
        $sponsors = Cache::remember('public:sponsor', now()->addMinutes(30), function () {
            return Sponsor::with('media')
                ->orderBy('tier')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'tier' => $s->tier,
                    'website_url' => $s->url,
                    'logo_url' => $s->getFirstMediaUrl('sponsor-logos'),
                    'sort_order' => $s->sort_order,
                ])->toArray();
        });

        $page = Page::where('slug', 'sponsor')->first();

        return Inertia::render('Public/Sponsor', [
            'sponsors' => $sponsors,
            'page' => $page,
        ]);
    }

    public function contatti()
    {
        $page = Page::where('slug', 'contatti')->first();

        return Inertia::render('Public/Contatti', [
            'page' => $page,
        ]);
    }

    public function shop()
    {
        $products = Cache::remember('public:shop', now()->addMinutes(10), function () {
            return Product::with('media')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug ?? null,
                    'description' => $p->description,
                    'price' => $p->price,
                    'stock' => $p->stock,
                    'image_url' => $p->getFirstMediaUrl('product-images'),
                ])->toArray();
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

    public function underConstruction()
    {
        return Inertia::render('Public/UnderConstruction');
    }
}
