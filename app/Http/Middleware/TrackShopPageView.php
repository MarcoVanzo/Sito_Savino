<?php

namespace App\Http\Middleware;

use App\Models\ShopEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackShopPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track GET requests that return 200
        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            // Extract serializable values before closure to avoid
            // capturing the full Request (contains non-serializable WeakMap)
            // Si conta la pagina, non chi la guarda: niente utente, sessione
            // né indirizzo IP, che nessuna statistica legge e che l'informativa
            // dello shop dichiara di non tenere (Privacy Policy, #acquisti).
            $fullUrl = $request->fullUrl();
            $referer = $request->header('Referer');

            dispatch(function () use ($fullUrl, $referer) {
                ShopEvent::create([
                    'event_type' => 'view',
                    'metadata' => [
                        'url' => $fullUrl,
                        'referer' => $referer,
                    ],
                ]);
            })->afterResponse();
        }

        return $response;
    }
}
