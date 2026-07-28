<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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
            $this->needsFreshCsrfCookie($request) ||
            $this->mightBeAuthenticated($request) ||
            $request->user() ||
            $request->hasHeader('X-Inertia') || // Inertia partial reloads need fresh data
            // I crawler social ricevono da ServeSocialCrawlerMeta un HTML
            // minimale con i soli meta og:. La chiave di cache non contiene lo
            // User-Agent, quindi senza questa esclusione quella risposta ridotta
            // veniva memorizzata e poi servita a TUTTI i visitatori anonimi
            // (cache poisoning). Le richieste dei crawler non vanno né lette
            // né scritte in cache.
            ServeSocialCrawlerMeta::isSocialCrawler($request)
        ) {
            return $next($request);
        }

        // Derive locale from URL prefix (this middleware runs before SetLocale)
        $locale = str_starts_with($request->getPathInfo(), '/en') ? 'en' : 'it';
        // Hash non crittografico: è solo una chiave di cache, non un contesto di sicurezza.
        $cacheKey = self::CACHE_PREFIX.hash('xxh128', $request->fullUrl().'|'.$locale);

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
            $registry = Cache::get(self::CACHE_PREFIX.'registry', []);
            if (! in_array($cacheKey, $registry, true)) {
                $registry[] = $cacheKey;
                Cache::put(self::CACHE_PREFIX.'registry', $registry, self::TTL * 2);
            }

            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Questo middleware gira in "prepend", cioè PRIMA di StartSession: a questo
     * punto $request->user() è sempre null e non è affidabile per distinguere
     * gli utenti autenticati. Senza questo controllo le pagine autenticate (es.
     * /profile) verrebbero messe in cache e servite a chiunque.
     *
     * Un utente autenticato ha sempre il cookie di sessione: se il cookie è
     * presente la richiesta è potenzialmente autenticata e la saltiamo, lasciando
     * girare l'intera pipeline (che gestisce correttamente auth e dati per-utente).
     * Il traffico realmente anonimo (bot, primo accesso senza cookie) continua a
     * beneficiare della cache full-page.
     */
    private function mightBeAuthenticated(Request $request): bool
    {
        return $request->cookies->has(Config::get('session.cookie'));
    }

    /**
     * Le pagine con form per ospiti (login, registrazione, recupero password)
     * devono NON essere messe in cache: la cache full-page rimuove gli header
     * Set-Cookie e X-XSRF-TOKEN, quindi un visitatore che le apre per primo non
     * riceverebbe il cookie XSRF-TOKEN e l'invio del form fallirebbe con 419.
     * Il frontend Inertia si affida esclusivamente a quel cookie per il CSRF.
     */
    private function needsFreshCsrfCookie(Request $request): bool
    {
        return $request->is(
            'login',
            'register',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            // Registrazione shop (slug localizzati)
            '*/registrati',
            'en/shop/register',
        );
    }

    /**
     * Flush only the page cache entries, not the entire cache store.
     * Called by CacheInvalidationObserver when models change.
     */
    public static function flush(): void
    {
        // With file cache driver we can't query by prefix,
        // so we maintain a registry of cached URLs.
        $registry = Cache::get(self::CACHE_PREFIX.'registry', []);

        foreach ($registry as $key) {
            Cache::forget($key);
        }

        Cache::forget(self::CACHE_PREFIX.'registry');
    }
}
