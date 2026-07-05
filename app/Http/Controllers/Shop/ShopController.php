<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
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
        // Products may have media in 'images' (WooCommerce migration) or 'products' (Filament admin).
        // Check both collections, preferring whichever has media.
        $media = $p->getMedia('products');
        if ($media->isEmpty()) {
            $media = $p->getMedia('images');
        }

        $firstMedia = $media->first();
        $imageUrl = null;
        if ($firstMedia) {
            // Only use conversion URL if the conversion file was actually generated
            if ($firstMedia->hasGeneratedConversion('card')) {
                $imageUrl = $firstMedia->getUrl('card');
            } else {
                $imageUrl = $firstMedia->getUrl();
            }
        }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'short_description' => $p->short_description,
            'price' => $p->price,
            'sale_price' => $p->sale_price,
            'stock' => $p->stock,
            'sku' => $p->sku,
            'is_active' => $p->is_active,
            'is_new' => $p->created_at?->greaterThan(now()->subDays(30)),
            'type' => $p->type?->value ?? $p->type,
            'category' => $p->category ? [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug ?? null,
            ] : null,
            'image_url' => $imageUrl,
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
        $media = $p->getMedia('products');
        if ($media->isEmpty()) {
            $media = $p->getMedia('images');
        }

        $imageUrl = $media->isNotEmpty()
            ? ($media->first()->getUrl('card') ?: $media->first()->getUrl())
            : null;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => $p->price,
            'sale_price' => $p->sale_price,
            'stock' => $p->stock,
            'is_new' => $p->created_at?->greaterThan(now()->subDays(30)),
            'category' => $p->category ? [
                'id' => $p->category->id,
                'name' => $p->category->name,
            ] : null,
            'image_url' => $imageUrl,
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

        $allProducts = Product::shoppable()
            ->with(['category', 'media'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => $this->mapProductCard($p))
            ->values();

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
            ->values();

        return Inertia::render('Public/Shop/Index', [
            'allProducts' => $allProducts,
            'categories' => $categories,
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
        if (! $product->is_active || $product->type === \App\Enums\ProductType::Auction) {
            abort(404);
        }

        $product->load(['variants', 'category', 'media']);

        $relatedProducts = Product::shoppable()
            ->where('product_category_id', $product->product_category_id)
            ->where('id', '!=', $product->id)
            ->with(['media'])
            ->inRandomOrder()
            ->take(4)
            ->get()
            ->map(fn ($p) => $this->mapProduct($p))
            ->values();

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

        $paginator = Product::shoppable()
            ->where('product_category_id', $category->id)
            ->with(['media', 'category'])
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(12)
            ->withQueryString();

        $paginator->through(fn ($p) => $this->mapProduct($p));

        return Inertia::render('Public/Shop/Category', [
            'category' => $category,
            'products' => $paginator,
            'currentSort' => $sort,
            'sortOptions' => array_keys($sortOptions),
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

            $paginator = Product::shoppable()
                ->where(function ($q) use ($escapedQuery) {
                    $q->where('name', 'LIKE', "%{$escapedQuery}%")
                      ->orWhere('description', 'LIKE', "%{$escapedQuery}%");
                })
                ->with(['media', 'category'])
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
}
