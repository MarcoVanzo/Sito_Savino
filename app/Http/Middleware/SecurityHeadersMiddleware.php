<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Aggiunge header di sicurezza a tutte le risposte HTTP.
     * CSP in modalità enforcing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            // La pagina Palazzetto incorpora Google Maps: senza questi due host
            // l'iframe non partiva affatto e restava un riquadro bianco.
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://www.google.com https://maps.google.com",
            "media-src 'self' https:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS: forza HTTPS per 1 anno (solo in produzione)
        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Finché si risponde sull'indirizzo di anteprima, fuori dai motori di
        // ricerca: il dominio ufficiale serve ancora il sito precedente e i due
        // sarebbero contenuto duplicato.
        //
        // Si dichiara `noindex` invece di vietare la scansione in robots.txt,
        // perché un indirizzo che Google non può leggere può comunque finire in
        // elenco: il divieto, per essere rispettato, va lasciato leggere.
        if (! $this->indexable($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function indexable(Request $request): bool
    {
        $hosts = array_map('strtolower', (array) config('app.indexable_hosts', []));

        return $hosts === [] || in_array(strtolower($request->getHost()), $hosts, true);
    }
}
