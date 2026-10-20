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
        @php
            $metaDellaPagina = request()->attributes->get(\App\Http\Middleware\ServeSocialCrawlerMeta::ATTRIBUTO_META);
        @endphp
        {{-- Con $metaDellaPagina (ServeSocialCrawlerMeta) l'HTML iniziale
             descrive la pagina vera anche senza SSR: titolo, descrizione e
             immagine di una notizia, di un prodotto, di un'atleta. --}}
        <meta name="description" content="{{ $metaDellaPagina['description'] ?? __('site.default_description') }}" inertia="description">
        <link rel="canonical" href="{{ url()->current() }}">
        @php
            $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
            $currentLocale = app()->getLocale();
            // Gli alternati si calcolano dal nome della rotta: gli slug sono
            // tradotti (/contatti, /en/contacts) e aggiungere o togliere `/en`
            // al percorso dichiarava indirizzi 404. Una lingua in cui la
            // pagina non esiste non si dichiara.
            $alternati = [];
            // `lingueDellaPagina` (withViewData): le lingue in cui la pagina
            // e' scritta davvero. Una scheda prodotto senza traduzione non
            // dichiara l'inglese e, aperta in inglese, e' noindex.
            $lingueDellaPagina = $lingueDellaPagina ?? null;
            foreach (\App\Support\IndirizziPerLingua::lingue() as $lingua) {
                if (is_array($lingueDellaPagina) && ! in_array($lingua, $lingueDellaPagina, true)) {
                    continue;
                }
                $indirizzo = $lingua === $currentLocale
                    ? url()->current()
                    : \App\Support\IndirizziPerLingua::perRichiesta(request(), $lingua, false);
                if ($indirizzo !== null) {
                    $alternati[$lingua] = $indirizzo;
                }
            }
            $predefinita = \App\Support\IndirizziPerLingua::linguaPredefinita();
        @endphp
        @if (is_array($lingueDellaPagina) && ! in_array($currentLocale, $lingueDellaPagina, true))
        <meta name="robots" content="noindex, follow">
        @endif
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
        <meta property="og:type" content="{{ $metaDellaPagina['type'] ?? 'website' }}" inertia="og:type">
        <meta property="og:site_name" content="Savino Del Bene Volley">
        <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'it_IT' }}">
        <meta property="og:title" content="{{ $metaDellaPagina['title'] ?? config('app.name', 'Savino Del Bene Volley') }}" inertia="og:title">
        {{-- L'indirizzo della pagina, non APP_URL: ogni pagina senza un suo
             og:url dichiarava di essere la home. --}}
        <meta property="og:url" content="{{ url()->current() }}" inertia="og:url">
        <meta property="og:image" content="{{ $metaDellaPagina['image'] ?? config('app.url').'/images/logo.png' }}" inertia="og:image">
        <meta property="og:description" content="{{ $metaDellaPagina['description'] ?? __('site.default_og_description') }}" inertia="og:description">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">

        <!-- Theme Color -->
        <meta name="theme-color" content="#003063">

        {{-- Dati strutturati (schema.org) calcolati sul server da
             ServeSocialCrawlerMeta / DatiStrutturati: la società e il sito in
             home, la notizia, il prodotto, la partita, l'atleta e le briciole
             di pane sulle altre pagine. Stanno nell'HTML servito, non li
             scrive il JavaScript. JSON_HEX_TAG: un tag di chiusura nel titolo
             non chiude lo script. --}}
        @foreach ($metaDellaPagina['jsonld'] ?? [] as $blocco)
        <script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode($blocco, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) !!}</script>
        @endforeach

        <title inertia>{{ $metaDellaPagina['title'] ?? config('app.name', 'Savino Del Bene Volley') }}</title>

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
