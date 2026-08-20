<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'preview' => [
        'user' => env('PREVIEW_AUTH_USER'),
        'pass' => env('PREVIEW_AUTH_PASS'),
    ],

    'compreface' => [
        'host' => env('COMPREFACE_HOST', 'http://localhost:8000'),
        'key' => env('COMPREFACE_KEY'),
    ],

    'activecampaign' => [
        'url' => env('ACTIVECAMPAIGN_URL'),
        'key' => env('ACTIVECAMPAIGN_API_KEY'),
        'list_id' => env('ACTIVECAMPAIGN_LIST_ID'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // 'sandbox' or 'live'
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WooCommerce (migrazione una tantum del vecchio shop)
    |--------------------------------------------------------------------------
    |
    | Il comando di migrazione leggeva queste variabili con env(): con la config
    | in cache, come in produzione, env() restituisce null e il comando partiva
    | con credenziali vuote.
    |
    */

    'woocommerce' => [
        'url' => env('WOOCOMMERCE_URL'),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY'),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lega Volley Femminile
    |--------------------------------------------------------------------------
    |
    | Calendario, risultati e classifica arrivano dal sito ufficiale della Lega,
    | che non espone API: le pagine pubbliche vengono scaricate e parsate.
    | `club_id` è l'identificativo del Savino Del Bene nell'anagrafica della
    | Lega e serve ad agganciare i dati remoti alla squadra già presente in
    | archivio invece di crearne un duplicato.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Wikipedia
    |--------------------------------------------------------------------------
    |
    | Sorgente del palmarès delle atlete. Si leggono le API MediaWiki pubbliche
    | (nessuna chiave), quindi qui c'è solo l'etichetta con cui ci si presenta:
    | le regole d'uso chiedono uno user agent che identifichi l'applicazione e
    | un contatto raggiungibile.
    |
    */

    'wikipedia' => [
        'lang' => env('WIKIPEDIA_LANG', 'it'),
        'timeout' => (int) env('WIKIPEDIA_TIMEOUT', 15),
        'user_agent' => env(
            'WIKIPEDIA_USER_AGENT',
            'SavinoDelBeneVolleyBot/1.0 (https://www.savinodelbenevolley.it; marketing@savinodelbenevolley.it)'
        ),
    ],

    'lvf' => [
        'base_url' => env('LVF_BASE_URL', 'https://www.legavolleyfemminile.it'),
        // I tabellini vivono su un host separato: è la pagina che il Match
        // Center carica dentro un iframe.
        'stats_base_url' => env('LVF_STATS_BASE_URL', 'https://ww5.legavolleyfemminile.it'),
        // La Lega rinumera gli identificativi di club a ogni stagione: qui vanno
        // elencati tutti quelli noti del Savino Del Bene, altrimenti importando
        // una stagione passata la squadra verrebbe duplicata.
        // 710955 = 2026/2027, 710918 = 2025/2026.
        'club_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('LVF_CLUB_IDS', '710955,710918'))
        ))),
        // Le pagine pubbliche della Lega elencano tutte le divisioni insieme.
        // Qui si dichiara quale campionato appartiene alla società: le gare
        // delle altre divisioni non vengono importate.
        'divisions' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LVF_DIVISIONS', 'a1'))
        ))),
        'excluded_divisions' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LVF_EXCLUDED_DIVISIONS', 'a2,a3,b1,b2'))
        ))),
        'timeout' => env('LVF_TIMEOUT', 30),
        // Il comando gira ogni ora e il sito della Lega ha cali temporanei: si
        // avvisa dopo tre giri a vuoto di fila (circa tre ore di buco), non al
        // primo errore, altrimenti l'avviso diventa rumore da ignorare.
        'failure_alert_threshold' => (int) env('LVF_FAILURE_ALERT_THRESHOLD', 3),
        // Finché il guasto dura il comando continua a fallire: dopo il primo
        // avviso si ripete una volta al giorno (24 fallimenti orari).
        'failure_alert_repeat_every' => (int) env('LVF_FAILURE_ALERT_REPEAT_EVERY', 24),
        'user_agent' => env(
            'LVF_USER_AGENT',
            'SavinoDelBeneVolleyBot/1.0 (+https://www.savinodelbenevolley.it; sincronizzazione calendario)'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4 — Data API
    |--------------------------------------------------------------------------
    |
    | Le statistiche del sito si leggono con un service account, non con OAuth
    | per utente: la property GA4 aggiunge l'email del service account come
    | Visualizzatore e basta. Niente schermata di consenso Google da far
    | verificare, niente token da rinnovare.
    |
    | Il JSON del service account NON sta nel repository (che è pubblico): o un
    | file fuori dal repo, o la variabile con il JSON (anche base64) iniettata
    | come secret da DigitalOcean App Platform.
    |
    */

    'ga4' => [
        'service_account_file' => env('GA4_SERVICE_ACCOUNT_FILE'),
        'service_account_json' => env('GA4_SERVICE_ACCOUNT_JSON'),
        'timeout' => (int) env('GA4_TIMEOUT', 30),
        // Fuso in cui GA4 chiude la giornata per le nostre property. Le date
        // relative ("28daysAgo", "today") sono risolte da Google in questo fuso:
        // le chiavi della serie giornaliera devono essere calcolate uguale,
        // altrimenti l'ultimo giorno risulta sempre vuoto.
        'timezone' => env('GA4_TIMEZONE', 'Europe/Rome'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta (Instagram + Facebook) — Graph API
    |--------------------------------------------------------------------------
    |
    | Un'unica app Meta serve tutti gli account social della società: ogni
    | account business si collega con il proprio OAuth ("Facebook Login for
    | Business") e il token finisce cifrato in `social_accounts`.
    |
    | `config_id` è l'ID della configurazione di Facebook Login for Business:
    | quando c'è sostituisce l'elenco degli scope. La configurazione deve
    | includere `read_insights`, altrimenti le metriche della Pagina arrivano
    | vuote SENZA errore.
    |
    */

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'config_id' => env('META_CONFIG_ID'),
        // Normalmente si ricava dalla rotta di callback: la variabile serve
        // solo se il dominio pubblico non coincide con APP_URL (anteprime,
        // ambienti dietro proxy), perché Meta pretende la corrispondenza
        // esatta con l'URI dichiarato nell'app.
        'redirect_uri' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
        'timeout' => (int) env('META_TIMEOUT', 30),
        // Meta consolida gli insight con un paio di giorni di ritardo: prima di
        // allora un giorno già scaricato può ancora cambiare.
        'data_delay_days' => (int) env('META_DATA_DELAY_DAYS', 2),
        // Fuso in cui Meta chiude la giornata per gli account italiani.
        'timezone' => env('META_TIMEZONE', 'Europe/Rome'),

        // Il Pixel misura il funnel pubblicitario sul sito, cosa diversa dagli
        // insight letti via Graph API: l'ID sta nelle impostazioni del pannello
        // perché non è un segreto (il browser lo espone comunque) e cambia
        // senza bisogno di un rilascio.
        //
        // Oggi il pixel si carica per tutti. Portarlo sotto il consenso
        // marketing del banner cookie è questa variabile, non un refactoring.
        'pixel_requires_consent' => filter_var(env('META_PIXEL_REQUIRES_CONSENT', false), FILTER_VALIDATE_BOOL),
    ],

];
