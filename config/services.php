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
        'timeout' => env('LVF_TIMEOUT', 30),
        'user_agent' => env(
            'LVF_USER_AGENT',
            'SavinoDelBeneVolleyBot/1.0 (+https://www.savinodelbenevolley.it; sincronizzazione calendario)'
        ),
    ],

];
