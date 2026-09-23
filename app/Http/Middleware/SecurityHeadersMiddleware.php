<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /** Sorgente "lo stesso sito" nelle direttive della Content Security Policy. */
    private const SELF = "'self'";

    /**
     * L'host del Pixel di Meta, che compare in tre direttive diverse: manda lì
     * gli eventi (`connect-src`), per spedirli apre un iframe verso il proprio
     * endpoint (`frame-src`) e in parte li invia come form (`form-action`).
     * Non è una piattaforma incorporabile e non va aggiunto a `LiveStream`.
     */
    private const PIXEL_DI_META = 'https://www.facebook.com';

    /**
     * I percorsi serviti dal pannello, dove la policy resta larga. Stesso
     * elenco di `bootstrap/app.php`: il pannello è Filament, cioè Livewire e
     * Alpine, e Alpine valuta le espressioni dei template con `new Function`.
     * Senza `unsafe-eval` il pannello non si disegna, e senza `unsafe-inline`
     * non parte nessuno dei suoi script di avvio.
     */
    private const PERCORSI_DEL_PANNELLO = ['admin', 'admin/*', 'filament/*', 'livewire/*'];

    /**
     * Aggiunge header di sicurezza a tutte le risposte HTTP.
     * CSP in modalità enforcing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $pannello = $request->is(...self::PERCORSI_DEL_PANNELLO);

        // Il nonce va deciso prima che la pagina venga disegnata: è `@vite` a
        // stamparlo sui tag che genera, e `app.blade.php` lo passa a `@routes`.
        if (! $pannello) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->csp($pannello));
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

    /**
     * La policy cambia solo nella direttiva degli script, e solo fra pannello e
     * sito pubblico.
     *
     * Sul pubblico gli script sono due: i bundle di Vite, serviti da `self`, e
     * l'unico blocco in linea della pagina, quello delle rotte di Ziggy, che
     * porta il nonce. GA4 e il Pixel non aggiungono codice in linea — li
     * caricano da `googletagmanager.com` e `connect.facebook.net` creando un
     * tag con `src` (vedi `analytics.js` e `meta-pixel.js`) — e Vue arriva coi
     * template già compilati, quindi non gli serve valutare stringhe.
     *
     * Con `'nonce-…'` in elenco i browser moderni ignorano `'unsafe-inline'`
     * anche se lo trovano: un XSS che riesca a iniettare un `<script>` nel
     * contenuto del CMS non ne conosce il valore, che cambia a ogni richiesta,
     * e resta lettera morta. È la differenza fra avere una CSP e averla scritta.
     *
     * Una pagina servita da `CachePublicResponse` ripete per un minuto il nonce
     * con cui è stata costruita, perché quella cache tiene insieme l'HTML e le
     * sue intestazioni. Non è un buco: il valore resta imprevedibile prima di
     * essere emesso, ed è la coerenza fra i due a contare — separarli
     * lascerebbe la pagina senza script.
     */
    private function csp(bool $pannello): string
    {
        // Gli stili in linea restano ammessi: sono quelli che Vue scrive negli
        // attributi `style` e Filament nei suoi componenti. Un foglio di stile
        // non esegue codice, e legarli tutti al nonce vorrebbe dire riscrivere
        // ogni `:style` del frontend.
        $fogliDiStile = [self::SELF, "'unsafe-inline'"];
        $font = [self::SELF];

        if ($pannello) {
            $scriptSrc = [self::SELF, "'unsafe-inline'", "'unsafe-eval'"];

            // Il pannello prende il font Outfit da fonts.bunny.net: è una
            // scelta di Filament, non nostra, e finché il pannello rispondeva
            // senza CSP passava inosservata. Senza questi due host il foglio
            // viene bloccato e il pannello si disegna col font di ripiego.
            $fogliDiStile[] = 'https://fonts.bunny.net';
            $font[] = 'https://fonts.bunny.net';
        } else {
            $nonce = Vite::cspNonce();

            $scriptSrc = [
                self::SELF,
                $nonce ? "'nonce-{$nonce}'" : "'unsafe-inline'",
                'https://www.googletagmanager.com',
                'https://connect.facebook.net',
            ];
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $fogliDiStile),
            'font-src '.implode(' ', $font),
            "img-src 'self' data: https:",
            // Dove le due misurazioni spediscono i dati raccolti. Senza,
            // caricare lo script non sarebbe comunque servito a niente.
            'connect-src '.implode(' ', [
                self::SELF,
                'https://www.google-analytics.com',
                'https://*.google-analytics.com',
                'https://*.analytics.google.com',
                'https://www.googletagmanager.com',
                'https://connect.facebook.net',
                self::PIXEL_DI_META,
            ]),
            // Gli unici host che possono finire dentro un iframe: Google Maps
            // per la pagina Palazzetto e le quattro piattaforme di diretta che
            // `App\Support\LiveStream` sa incorporare. I due elenchi vanno
            // tenuti allineati: un link accettato lì e vietato qui passerebbe i
            // controlli per poi restare un riquadro bianco, bloccato dal
            // browser senza che si capisca perché.
            // Dailymotion serve il player da `geo.` dopo una redirezione, e la
            // redirezione viene verificata come la richiesta iniziale.
            // `www.facebook.com` non è una piattaforma incorporabile e non va
            // aggiunto a LiveStream: è il pixel, che per spedire gli eventi
            // apre un iframe verso il proprio endpoint. Bloccarlo lasciava
            // metà delle conversioni per strada senza dirlo a nessuno —
            // l'errore si leggeva solo nella console del visitatore.
            'frame-src '.implode(' ', [
                self::SELF,
                'https://www.google.com', 'https://maps.google.com',
                'https://www.youtube.com', 'https://www.youtube-nocookie.com',
                'https://player.vimeo.com',
                'https://player.twitch.tv',
                'https://www.dailymotion.com', 'https://geo.dailymotion.com',
                self::PIXEL_DI_META,
            ]),
            "media-src 'self' https:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            // Il pixel di Meta spedisce gli eventi anche come form verso
            // `facebook.com/tr/`, e `'self'` da solo li rifiutava: il tag si
            // caricava, la misurazione no. Resta l'unico host esterno a cui
            // una pagina di questo sito può inviare un modulo.
            'form-action '.implode(' ', [self::SELF, self::PIXEL_DI_META]),
        ]);
    }

    private function indexable(Request $request): bool
    {
        $hosts = array_map('strtolower', (array) config('app.indexable_hosts', []));

        return $hosts === [] || in_array(strtolower($request->getHost()), $hosts, true);
    }
}
