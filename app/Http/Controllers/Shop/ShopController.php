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
     * Shop homepage.
     * Se lo shop è disabilitato, mostra la pagina di manutenzione.
     */
    public function index(Request $request): Response
    {
        if (! filter_var(SiteSetting::get('shop.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return Inertia::render('Public/Shop/Maintenance');
        }

        $featuredProducts = Product::shoppable()
            ->with(['category', 'media'])
            ->latest()
            ->take(8)
            ->get();

        $categories = ProductCategory::withCount(['products' => function ($query) {
                $query->shoppable();
            }])
            ->ordered()
            ->get();

        return Inertia::render('Public/Shop/Index', [
            'featuredProducts' => $featuredProducts,
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
            ->get();

        return Inertia::render('Public/Shop/ProductDetail', [
            'product' => $product,
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

        $products = Product::shoppable()
            ->where('product_category_id', $category->id)
            ->with(['media'])
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Public/Shop/Category', [
            'category' => $category,
            'products' => $products,
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

            $products = Product::shoppable()
                ->where(function ($q) use ($escapedQuery) {
                    $q->where('name', 'LIKE', "%{$escapedQuery}%")
                      ->orWhere('description', 'LIKE', "%{$escapedQuery}%");
                })
                ->with(['media', 'category'])
                ->latest()
                ->paginate(12)
                ->withQueryString();
        }

        return Inertia::render('Public/Shop/Search', [
            'query' => $query,
            'products' => $products,
        ]);
    }
}
