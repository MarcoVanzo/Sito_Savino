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
            dispatch(function () use ($request) {
                ShopEvent::create([
                    'event_type' => 'view',
                    'user_id' => $request->user()?->id,
                    'session_id' => $request->session()->getId(),
                    'ip_address' => $request->ip(),
                    'metadata' => [
                        'url' => $request->fullUrl(),
                        'referer' => $request->header('Referer'),
                    ],
                ]);
            })->afterResponse();
        }

        return $response;
    }
}
