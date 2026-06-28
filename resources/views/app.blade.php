<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.">
        <link rel="canonical" href="{{ url()->current() }}">

        <!-- Open Graph -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Savino Del Bene Volley">
        <meta property="og:locale" content="it_IT">
        <meta property="og:title" content="{{ config('app.name', 'Savino Del Bene Volley') }}">
        <meta property="og:url" content="{{ config('app.url') }}">
        <meta property="og:image" content="{{ config('app.url') }}/images/logo.png">
        <meta property="og:description" content="Savino Del Bene Volley - Sito ufficiale della squadra di pallavolo femminile di Scandicci. Serie A1, roster, calendario, risultati e shop.">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">

        <!-- Theme Color -->
        <meta name="theme-color" content="#003063">

        <!-- Structured Data -->
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "SportsTeam",
            "name": "Savino Del Bene Volley",
            "sport": "Volleyball",
            "url": "{{ config('app.url') }}",
            "logo": "{{ config('app.url') }}/images/logo.png",
            "location": {
                "@@type": "Place",
                "name": "Palazzo Wanny",
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
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "name": "Savino Del Bene Volley",
            "url": "{{ config('app.url') }}",
            "inLanguage": "it-IT",
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

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
