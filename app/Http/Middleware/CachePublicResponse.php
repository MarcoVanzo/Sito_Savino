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
 * Busted:   automatically via TTL (60 s), on model save via
 *           CacheInvalidationObserver, or manually via
 *           `php artisan cache:clear`.
 *
 * Security: Set-Cookie headers are NEVER cached. Each response
 *           served from cache is cookie-free; the browser keeps
 *           whatever cookies it already has from previous requests.
 */
class CachePublicResponse
{
    /** Cache TTL in seconds. */
    private const TTL = 60;

    /** Cache key prefix for targeted invalidation. */
    public const CACHE_PREFIX = 'page_cache:';

    /**
     * Headers that must NEVER be cached because they are
     * user-specific or security-sensitive.
     */
    private const EXCLUDED_HEADERS = [
        'set-cookie',
        'x-xsrf-token',
    ];

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

        $cacheKey = self::CACHE_PREFIX . md5($request->fullUrl());

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
            // Strip security-sensitive headers before caching
            $safeHeaders = [];
            foreach ($response->headers->all() as $name => $values) {
                if (! in_array(strtolower($name), self::EXCLUDED_HEADERS, true)) {
                    $safeHeaders[$name] = $values[0] ?? $values;
                }
            }

            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $safeHeaders,
            ], self::TTL);

            // Track this key in the registry for selective flush
            $registry = Cache::get(self::CACHE_PREFIX . 'registry', []);
            if (! in_array($cacheKey, $registry, true)) {
                $registry[] = $cacheKey;
                Cache::put(self::CACHE_PREFIX . 'registry', $registry, self::TTL * 2);
            }

            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Flush only the page cache entries, not the entire cache store.
     * Called by CacheInvalidationObserver when models change.
     */
    public static function flush(): void
    {
        // With file cache driver we can't query by prefix,
        // so we maintain a registry of cached URLs.
        $registry = Cache::get(self::CACHE_PREFIX . 'registry', []);

        foreach ($registry as $key) {
            Cache::forget($key);
        }

        Cache::forget(self::CACHE_PREFIX . 'registry');
    }
}
