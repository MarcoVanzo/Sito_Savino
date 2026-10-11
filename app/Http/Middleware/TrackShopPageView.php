<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\ShopEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackShopPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->eUnaPaginaVista($request, $response)) {
            return $response;
        }

        $product = $request->route('product');

        self::registra($request, $product instanceof Product ? $product->getKey() : null);

        return $response;
    }

    /**
     * Una pagina dello shop servita da CachePublicResponse: la pipeline non
     * arriva fino a questo middleware, e senza questo si perdevano proprio i
     * visitatori senza cookie, come chi apre un prodotto da un link social.
     * La cache tiene solo pagine HTML con risposta 200: sono tutte visite.
     * Il prodotto si ricava dallo slug, perché qui le rotte non sono ancora
     * risolte.
     */
    public static function registraDallaCache(Request $request): void
    {
        if (! $request->is('shop', 'shop/*', 'en/shop', 'en/shop/*')) {
            return;
        }

        $productId = preg_match('#^(?:en/)?shop/(?:prodotto|product)/([^/]+)$#', $request->path(), $trovato)
            ? Product::query()->where('slug', rawurldecode($trovato[1]))->value('id')
            : null;

        self::registra($request, $productId);
    }

    /**
     * Si conta la pagina, non chi la guarda: niente utente, sessione né
     * indirizzo IP, che nessuna statistica legge e che l'informativa dello
     * shop dichiara di non tenere (Privacy Policy, #acquisti). Il prodotto
     * serve al grafico «Prodotti più visti» (PageViewsWidget), che raggruppa
     * su viewable: senza restava vuoto.
     */
    private static function registra(Request $request, ?int $productId): void
    {
        // Extract serializable values before closure to avoid
        // capturing the full Request (contains non-serializable WeakMap)
        $fullUrl = $request->fullUrl();
        $referer = $request->header('Referer');
        $viewableType = $productId !== null ? (new Product)->getMorphClass() : null;

        dispatch(function () use ($fullUrl, $referer, $viewableType, $productId) {
            ShopEvent::create([
                'event_type' => 'view',
                'viewable_type' => $viewableType,
                'viewable_id' => $productId,
                'metadata' => [
                    'url' => $fullUrl,
                    'referer' => $referer,
                ],
            ]);
        })->afterResponse();
    }

    /**
     * Solo le pagine aperte da qualcuno: né le chiamate del carrello
     * (`carrello/count`, `carrello/data`, JSON a ogni pagina, erano l'85%
     * delle «visite»), né le ricevute PDF, né i prefetch del menu al
     * passaggio del mouse, né i ricaricamenti parziali di Inertia.
     */
    private function eUnaPaginaVista(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        if ($request->header('Purpose') === 'prefetch' || $request->hasHeader('X-Inertia-Partial-Data')) {
            return false;
        }

        return $response->headers->has('X-Inertia')
            || str_contains($response->headers->get('Content-Type', ''), 'text/html');
    }
}
