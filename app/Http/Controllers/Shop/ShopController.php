<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    /**
     * Mappa un Product Eloquent model in un array con campi tradotti e image_url.
     * Spatie HasTranslations serializza i campi translatable come oggetto JSON
     * con tutte le lingue; accedendo via accessor ($p->name) otteniamo la traduzione
     * per la locale corrente.
     */
    private function mapProduct(Product $p): array
    {
        // Resolve media from both collections (products > images) via model helper
        $media = $p->getMedia('products');
        if ($media->isEmpty()) {
            $media = $p->getMedia('images');
        }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'short_description' => $p->short_description,
            'price' => $p->price,
            'sale_price' => $p->sale_price,
            'stock' => $p->availableStock(),
            'sku' => $p->sku,
            'is_active' => $p->is_active,
            'is_new' => $p->created_at?->greaterThan(now()->subDays(30)),
            'type' => $p->type->value ?? $p->type,
            'category' => $p->category ? [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug ?? null,
            ] : null,
            'image_url' => $p->getImageUrl('card'),
            'images' => $media->map(fn ($m) => $m->getUrl())->values()->all(),
            'variants' => $p->relationLoaded('variants') ? $p->variants : [],
        ];
    }

    /**
     * Mapper leggero per la griglia prodotti (card).
     * Evita di mandare description, images[], variants al client per ogni prodotto.
     */
    private function mapProductCard(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => $p->price,
            'sale_price' => $p->sale_price,
            'stock' => $p->availableStock(),
            'type' => $p->type->value ?? $p->type,
            'is_new' => $p->created_at?->greaterThan(now()->subDays(30)),
            'category' => $p->category ? [
                'id' => $p->category->id,
                'name' => $p->category->name,
            ] : null,
            'image_url' => $p->getImageUrl('card'),
        ];
    }

    /**
     * Shop homepage.
     * Se lo shop è disabilitato, mostra la pagina di manutenzione.
     */
    public function index(Request $request): Response
    {
        if (! filter_var(SiteSetting::get('shop.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return Inertia::render('Public/Shop/Maintenance');
        }

        $locale = app()->getLocale();
        $data = Cache::remember("public:shop:{$locale}", now()->addMinutes(10), function () {
            $allProducts = Product::shoppable()
                ->with(['category', 'media'])
                ->withSum('variants', 'stock')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => $this->mapProductCard($p))
                ->values()
                ->all();

            $categories = ProductCategory::withCount(['products' => function ($query) {
                $query->shoppable();
            }])
                ->ordered()
                ->get()
                ->filter(fn ($c) => $c->products_count > 0)
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'products_count' => $c->products_count,
                ])
                ->values()
                ->all();

            return compact('allProducts', 'categories');
        });

        return Inertia::render('Public/Shop/Index', [
            'allProducts' => $data['allProducts'],
            'categories' => $data['categories'],
            'announcementBanner' => SiteSetting::get('shop.announcement_banner'),
        ]);
    }

    /**
     * Pagina dettaglio prodotto.
     * La view viene tracciata dal middleware TrackShopPageView.
     */
    public function productShow(Request $request, Product $product): Response
    {
        // Solo prodotti attivi e non di tipo Auction
        if (! $product->is_active || $product->type === ProductType::Auction) {
            abort(404);
        }

        $product->load(['variants', 'category', 'media']);

        $relatedCacheKey = 'product:'.$product->id.':related:'.app()->getLocale();
        $relatedProducts = Cache::remember($relatedCacheKey, now()->addMinutes(30), function () use ($product) {
            return Product::shoppable()
                ->when($product->product_category_id, fn ($q) => $q->where('product_category_id', $product->product_category_id))
                ->where('id', '!=', $product->id)
                ->with(['media', 'category'])
                ->withSum('variants', 'stock')
                ->orderByRaw('RAND(?)', [$product->id])
                ->take(4)
                ->get()
                ->map(fn ($p) => $this->mapProductCard($p))
                ->values();
        });

        return Inertia::render('Public/Shop/ProductDetail', [
            'product' => $this->mapProduct($product),
            'relatedProducts' => $relatedProducts,
        ]);
    }

    /**
     * Pagina categoria con prodotti filtrati, paginati e ordinabili.
     */
    public function categoryShow(Request $request, ProductCategory $category): Response
    {
        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
        ];

        $sort = $request->get('sort', 'newest');
        if (! array_key_exists($sort, $sortOptions)) {
            $sort = 'newest';
        }

        [$sortColumn, $sortDirection] = $sortOptions[$sort];

        // Le sottocategorie dividono un reparto in scaffali — Kit Gara in Home,
        // Away e Champions — e si comportano come i ruoli nel roster: filtrano
        // senza cambiare pagina. Restano solo quelle che hanno qualcosa dentro,
        // altrimenti si clicca su un elenco vuoto.
        $sottocategorie = $category->children()
            ->withCount(['products' => fn ($query) => $query->shoppable()])
            ->ordered()
            ->get()
            ->filter(fn ($figlia) => $figlia->products_count > 0)
            ->map(fn ($figlia) => [
                'id' => $figlia->id,
                'name' => $figlia->name,
                'slug' => $figlia->slug,
                'products_count' => $figlia->products_count,
            ])
            ->values();

        $sottocategoriaAttiva = $request->get('gruppo');

        if ($sottocategoriaAttiva !== null && ! $sottocategorie->contains('slug', $sottocategoriaAttiva)) {
            $sottocategoriaAttiva = null;
        }

        // Senza figlie si guarda il reparto; con le figlie si guarda uno
        // scaffale, o tutti gli scaffali insieme.
        $categorieMostrate = match (true) {
            $sottocategoriaAttiva !== null => [$sottocategorie->firstWhere('slug', $sottocategoriaAttiva)['id']],
            $sottocategorie->isNotEmpty() => [$category->id, ...$sottocategorie->pluck('id')->all()],
            default => [$category->id],
        };

        $paginator = Product::shoppable()
            ->whereIn('product_category_id', $categorieMostrate)
            ->with(['media', 'category'])
            ->withSum('variants', 'stock')
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(12)
            ->withQueryString();

        $paginator->through(fn ($p) => $this->mapProduct($p));

        return Inertia::render('Public/Shop/Category', [
            'category' => $category,
            'products' => $paginator,
            'currentSort' => $sort,
            'sortOptions' => array_keys($sortOptions),
            'subcategories' => $sottocategorie,
            'activeSubcategory' => $sottocategoriaAttiva,
        ]);
    }

    /**
     * Ricerca prodotti per nome/descrizione.
     */
    public function search(Request $request): Response
    {
        $query = $request->get('q', '');

        $products = collect();

        if (strlen(trim($query)) >= 2) {
            $escapedQuery = str_replace(['%', '_'], ['\\%', '\\_'], $query);
            $supportedLocales = config('app.supported_locales', ['it', 'en']);
            $locale = in_array(app()->getLocale(), $supportedLocales, true) ? app()->getLocale() : 'it';

            $paginator = Product::shoppable()
                ->where(function ($q) use ($escapedQuery, $locale) {
                    // JSON_UNQUOTE restituisce utf8mb4_bin: senza COLLATE esplicito
                    // la LIKE distingue maiuscole e accenti ("Maglia" e "maglia"
                    // davano risultati diversi).
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) COLLATE utf8mb4_unicode_ci LIKE ?", ["%{$escapedQuery}%"])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) COLLATE utf8mb4_unicode_ci LIKE ?", ["%{$escapedQuery}%"]);
                })
                ->with(['media', 'category'])
                ->withSum('variants', 'stock')
                ->latest()
                ->paginate(12)
                ->withQueryString();

            $paginator->through(fn ($p) => $this->mapProduct($p));
            $products = $paginator;
        }

        return Inertia::render('Public/Shop/Search', [
            'query' => $query,
            'products' => $products,
        ]);
    }

    /**
     * Pagina Guida Taglie con documenti PDF scaricabili.
     */
    public function sizeGuide(): Response
    {
        $sizeGuides = SiteSetting::get('shop.size_guides');
        if (is_string($sizeGuides)) {
            $sizeGuides = json_decode($sizeGuides, true) ?? [];
        }

        // Convert file paths to full URLs
        $guides = collect($sizeGuides ?? [])->map(fn ($path) => [
            'path' => $path,
            'url' => asset('storage/'.$path),
            'name' => pathinfo($path, PATHINFO_FILENAME),
        ])->values()->all();

        return Inertia::render('Public/Shop/SizeGuide', [
            'sizeGuides' => $guides,
            'supportEmail' => SiteSetting::get('shop.support_email'),
        ]);
    }

    /**
     * Pagina Contatti Shop con informazioni assistenza.
     */
    public function shopContacts(): Response
    {
        return Inertia::render('Public/Shop/ShopContacts', [
            'supportEmail' => SiteSetting::get('shop.support_email'),
            'supportPhone' => SiteSetting::get('shop.support_phone'),
            'supportHours' => SiteSetting::get('shop.support_hours'),
            'supportNotes' => SiteSetting::get('shop.support_notes'),
        ]);
    }
}
