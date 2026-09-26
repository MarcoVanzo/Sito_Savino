<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Description e og: portano l'attributo `inertia` con una chiave: al
             primo render il gestore del <Head> di Inertia li toglie e al loro
             posto restano quelli della pagina. Senza, nel DOM ce n'erano due
             di ciascuno (questi generici e quelli della pagina) e Google
             poteva prendere il primo. Ai crawler dei social risponde
             ServeSocialCrawlerMeta (CLAUDE.md §19), non questo layout. --}}
        <meta name="description" content="{{ __('site.default_description') }}" inertia="description">
        <link rel="canonical" href="{{ url()->current() }}">
        @php
            $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
            $currentLocale = app()->getLocale();
            // Gli alternati si calcolano dal nome della rotta: gli slug sono
            // tradotti (/contatti, /en/contacts) e aggiungere o togliere `/en`
            // al percorso dichiarava indirizzi 404. Una lingua in cui la
            // pagina non esiste non si dichiara.
            $alternati = [];
            foreach (\App\Support\IndirizziPerLingua::lingue() as $lingua) {
                $indirizzo = $lingua === $currentLocale
                    ? url()->current()
                    : \App\Support\IndirizziPerLingua::perRichiesta(request(), $lingua, false);
                if ($indirizzo !== null) {
                    $alternati[$lingua] = $indirizzo;
                }
            }
            $predefinita = \App\Support\IndirizziPerLingua::linguaPredefinita();
        @endphp
        @if (count($alternati) > 1)
            @foreach ($alternati as $lingua => $indirizzo)
        <link rel="alternate" hreflang="{{ $lingua }}" href="{{ $indirizzo }}">
            @endforeach
            @isset($alternati[$predefinita])
        <link rel="alternate" hreflang="x-default" href="{{ $alternati[$predefinita] }}">
            @endisset
        @endif

        {{-- Feed RSS delle notizie: è così che un lettore automatico lo trova
             senza che gli si dia l'indirizzo. --}}
        <link rel="alternate" type="application/rss+xml"
              title="{{ __('site.feed.title') }}"
              href="{{ \App\Services\NewsFeedBuilder::indirizzo($currentLocale) }}">

        <!-- Open Graph -->
        <meta property="og:type" content="website" inertia="og:type">
        <meta property="og:site_name" content="Savino Del Bene Volley">
        <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'it_IT' }}">
        <meta property="og:title" content="{{ config('app.name', 'Savino Del Bene Volley') }}" inertia="og:title">
        {{-- L'indirizzo della pagina, non APP_URL: ogni pagina senza un suo
             og:url dichiarava di essere la home. --}}
        <meta property="og:url" content="{{ url()->current() }}" inertia="og:url">
        <meta property="og:image" content="{{ config('app.url') }}/images/logo.png" inertia="og:image">
        <meta property="og:description" content="{{ __('site.default_og_description') }}" inertia="og:description">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">

        <!-- Theme Color -->
        <meta name="theme-color" content="#003063">

        <!-- Structured Data -->
        <script type="application/ld+json" nonce="{{ $cspNonce }}">
        {
            "@@context": "https://schema.org",
            "@@type": "SportsTeam",
            "name": "Savino Del Bene Volley",
            "sport": "Volleyball",
            "url": "{{ config('app.url') }}",
            "logo": "{{ config('app.url') }}/images/logo.png",
            "location": {
                "@@type": "Place",
                "name": "Pala BigMat",
                "address": {
                    "@@type": "PostalAddress",
                    "addressLocality": "Firenze",
                    "addressRegion": "Toscana",
                    "addressCountry": "IT"
                }
            },
            "memberOf": {
                "@@type": "SportsOrganization",
                "name": "Lega Pallavolo Serie A Femminile"
            }
        }
        </script>

        <!-- WebSite Structured Data -->
        <script type="application/ld+json" nonce="{{ $cspNonce }}">
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "name": "Savino Del Bene Volley",
            "url": "{{ config('app.url') }}",
            "inLanguage": "{{ app()->getLocale() === 'en' ? 'en-US' : 'it-IT' }}",
            "publisher": {
                "@@type": "Organization",
                "name": "Savino Del Bene Volley",
                "logo": "{{ config('app.url') }}/images/logo.png"
            }
        }
        </script>

        <title inertia>{{ config('app.name', 'Savino Del Bene Volley') }}</title>

        <!-- Favicon -->
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
        <link rel="apple-touch-icon" sizes="180x180" href="/images/logo.png">

        {{-- I caratteri li serve il sito: i @font-face stanno in resources/css/app.css,
             i file in public/fonts. Prima arrivavano dal CDN di Google, che vedeva
             l'IP di ogni visitatore prima ancora della scelta sui cookie. --}}
        <link rel="preload" href="/fonts/montserrat-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/playfair-display-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>

        <!-- Scripts -->
        @routes(null, $cspNonce)
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
