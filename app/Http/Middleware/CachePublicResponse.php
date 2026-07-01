<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full-page response cache for public GET requests.
 *
 * Caches the entire HTTP response so subsequent requests
 * skip the Laravel pipeline entirely (controller, Inertia,
 * Eloquent, etc.) and return in < 10 ms.
 *
 * Excluded: admin, filament, livewire, API, authenticated users.
 * Busted:   automatically via TTL (60 s) or manually via
 *           Artisan command `php artisan cache:clear`.
 */
class CachePublicResponse
{
    /** Cache TTL in seconds. */
    private const TTL = 60;

    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET requests on public pages for anonymous users
        if (
            ! $request->isMethod('GET') ||
            $request->is('admin*', 'filament*', 'livewire*', 'api*', '_debugbar*', '_ignition*') ||
            $request->user() ||
            $request->hasHeader('X-Inertia') // Inertia partial reloads need fresh data
        ) {
            return $next($request);
        }

        $cacheKey = 'page_cache:' . md5($request->fullUrl());

        $cached = Cache::get($cacheKey);

        if ($cached) {
            return response($cached['content'], $cached['status'])
                ->withHeaders($cached['headers'])
                ->header('X-Page-Cache', 'HIT');
        }

        /** @var Response $response */
        $response = $next($request);

        // Only cache successful HTML responses
        if (
            $response->getStatusCode() === 200 &&
            str_contains($response->headers->get('Content-Type', ''), 'text/html')
        ) {
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => array_map(
                    fn ($h) => $h[0] ?? $h,
                    $response->headers->all()
                ),
            ], self::TTL);

            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }
}
