<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use App\Services\StoricoPrezzi;
use App\Support\EtichetteDelProdotto;
use App\Support\GuidaTaglie;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    /**
     * Lo storico dei prezzi dei prodotti in sconto della lista che si sta
     * costruendo, letto con una query sola (precaricaLoStorico). Senza, ogni
     * card in sconto ne faceva una sua.
     *
     * @var array<int, Collection<int, \stdClass>>
     */
    private array $storico = [];

    /**
     * @param  iterable<Product>  $prodotti
     */
    private function precaricaLoStorico(iterable $prodotti): void
    {
        $this->storico = app(StoricoPrezzi::class)->righePer($prodotti);
    }

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

        $prezzi = $this->prezzi($p);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'short_description' => $p->short_description,
            ...$prezzi,
            'stock' => $p->availableStock(),
            'sku' => $p->sku,
            'is_active' => $p->is_active,
            'etichette' => EtichetteDelProdotto::per($p, $prezzi['prezzo_piu_basso_30_giorni']),
            'personalizzazione' => $p->offrePersonalizzazione() ? [
                'nome' => $p->personalizzazione_nome,
                'prezzo' => (float) $p->personalizzazione_prezzo,
            ] : null,
            'type' => $p->type->value ?? $p->type,
            'category' => $p->category ? [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug ?? null,
            ] : null,
            'image_url' => $p->getImageUrl('card'),
            'images' => $media->map(fn ($m) => $m->getUrl())->values()->all(),
            'variants' => $p->relationLoaded('variants') ? $p->variants : [],
            // Null quando la redazione ha tolto la voce o non c'e' nessuna
            // guida caricata: il frontend non mostra un link che non porta
            // da nessuna parte.
            'size_guide_url' => GuidaTaglie::perIlProdotto($p),
        ];
    }

    /**
     * Il prezzo come lo vede il cliente: `price` è quello barrato quando c'è
     * uno sconto annunciabile, `sale_price` quello che si paga.
     *
     * Lo sconto si annuncia solo mentre e' in corso (`sale_price` da sola
     * ignora la finestra sale_start/sale_end: un ribasso programmato si
     * vedeva gia' oggi e il carrello faceva pagare il prezzo pieno) e solo
     * con un prezzo precedente vero: il più basso dei 30 giorni prima della
     * riduzione (art. 17-bis del Codice del consumo, StoricoPrezzi). Prima si
     * barrava il listino. Senza un prezzo precedente praticato lo sconto non
     * si annuncia, ma si applica: `price` diventa il prezzo scontato, che è
     * quello che il carrello fa pagare.
     *
     * @return array{price: mixed, sale_price: mixed, prezzo_piu_basso_30_giorni: bool}
     */
    private function prezzi(Product $p): array
    {
        $riferimento = app(StoricoPrezzi::class)->prezzoDiRiferimento($p, $this->storico[$p->id] ?? null);

        if ($riferimento !== null) {
            return [
                'price' => number_format($riferimento, 2, '.', ''),
                'sale_price' => $p->sale_price,
                'prezzo_piu_basso_30_giorni' => true,
            ];
        }

        return [
            'price' => number_format($p->effectivePrice(), 2, '.', ''),
            'sale_price' => null,
            'prezzo_piu_basso_30_giorni' => false,
        ];
    }

    /**
     * Mapper leggero per la griglia prodotti (card).
     * Evita di mandare description, images[], variants al client per ogni prodotto.
     */
    private function mapProductCard(Product $p): array
    {
        $prezzi = $this->prezzi($p);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            ...$prezzi,
            'stock' => $p->availableStock(),
            'type' => $p->type->value ?? $p->type,
            'etichette' => EtichetteDelProdotto::per($p, $prezzi['prezzo_piu_basso_30_giorni']),
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
    public function index(): Response
    {
        if (! filter_var(SiteSetting::get('shop.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return Inertia::render('Public/Shop/Maintenance');
        }

        $locale = app()->getLocale();
        $data = Cache::remember("public:shop:{$locale}", now()->addMinutes(10), function () {
            $prodotti = Product::shoppable()
                ->with(['category', 'media'])
                ->withSum('variants', 'stock')
                ->withMax('variants', 'stock')
                ->orderBy('sort_order')
                ->get();

            $this->precaricaLoStorico($prodotti);

            $allProducts = $prodotti
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
    public function productShow(Product $product): Response
    {
        // Solo prodotti attivi e non di tipo Auction
        if (! $product->is_active || $product->type === ProductType::Auction) {
            abort(404);
        }

        $product->load(['variants', 'category', 'media']);

        return Inertia::render('Public/Shop/ProductDetail', [
            'product' => $this->mapProduct($product),
            'relatedProducts' => $this->prodottiCorrelati($product),
        ]);
    }

    /**
     * Gli articoli da mostrare sotto la scheda prodotto.
     *
     * Quelli scelti in redazione vengono prima e non si mettono in cache: e'
     * una lettura da poco e deve rispecchiare subito il pannello, altrimenti
     * chi li collega non li vede per mezz'ora e pensa di aver sbagliato. Il
     * ripiego a caso nella stessa categoria, che e' il comportamento storico,
     * resta in cache come prima, ma solo la scelta degli id: prezzo,
     * etichette e giacenza si leggono a ogni richiesta. In cache c'erano le
     * card intere, che nessuno buttava: per mezz'ora una card poteva dire IN
     * OFFERTA o ULTIMO RIMASTO a sconto finito o a taglia esaurita.
     */
    private function prodottiCorrelati(Product $product)
    {
        $scelti = $product->relatedProducts()
            ->shoppable()
            ->with(['media', 'category'])
            ->withSum('variants', 'stock')
            ->withMax('variants', 'stock')
            ->orderBy('sort_order')
            ->get();

        if ($scelti->isNotEmpty()) {
            return $this->carteDeiCorrelati($scelti);
        }

        $ids = Cache::remember('product:'.$product->id.':related_ids', now()->addMinutes(30), function () use ($product) {
            return Product::shoppable()
                ->when($product->product_category_id, fn ($q) => $q->where('product_category_id', $product->product_category_id))
                ->where('id', '!=', $product->id)
                ->orderByRaw('RAND(?)', [$product->id])
                ->take(4)
                ->pluck('id')
                ->all();
        });

        // Si riapplica shoppable(): un id in cache puo' essere stato ritirato
        // nel frattempo.
        $prodotti = Product::shoppable()
            ->whereIn('id', $ids)
            ->with(['media', 'category'])
            ->withSum('variants', 'stock')
            ->withMax('variants', 'stock')
            ->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
            ->values();

        return $this->carteDeiCorrelati($prodotti);
    }

    /**
     * @param  Collection<int, Product>  $prodotti
     */
    private function carteDeiCorrelati($prodotti)
    {
        $this->precaricaLoStorico($prodotti);

        return $prodotti->map(fn ($p) => $this->mapProductCard($p))->values();
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
            ->withMax('variants', 'stock')
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(12)
            ->withQueryString();

        $this->precaricaLoStorico($paginator->getCollection());
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
                ->withMax('variants', 'stock')
                ->latest()
                ->paginate(12)
                ->withQueryString();

            $this->precaricaLoStorico($paginator->getCollection());
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
        return Inertia::render('Public/Shop/SizeGuide', [
            'sizeGuides' => GuidaTaglie::documenti(),
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
