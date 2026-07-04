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
            $userId = $request->user()?->id;
            $sessionId = $request->session()->getId();
            $ipAddress = $request->ip();
            $fullUrl = $request->fullUrl();
            $referer = $request->header('Referer');

            dispatch(function () use ($userId, $sessionId, $ipAddress, $fullUrl, $referer) {
                ShopEvent::create([
                    'event_type' => 'view',
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'ip_address' => $ipAddress,
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
