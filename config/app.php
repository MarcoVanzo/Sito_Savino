<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Savino Del Bene Volley'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    // Se l'ambiente fornisce il solo dominio (DigitalOcean espande ${APP_DOMAIN}
    // senza schema), Laravel lo interpreterebbe come path e genererebbe URL
    // "https://localhost/<dominio>/…" in tutto ciò che nasce da CLI: sitemap,
    // email in coda, JSON-LD. Qui lo schema viene normalizzato una volta sola.
    'url' => (function (): string {
        $url = trim((string) env('APP_URL', 'http://localhost'));

        if ($url === '') {
            return 'http://localhost';
        }

        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1 ? $url : 'https://'.$url;
    })(),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Europe/Rome'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'it'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'it'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'it_IT'),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Lingue effettivamente pubblicate dal sito: prefissi di rotta, locali del
    | plugin translatable di Filament, suffissi delle chiavi di cache pubbliche.
    | UNICA fonte di verità: non ripetere ['it', 'en'] altrove nel codice, usare
    | sempre config('app.supported_locales').
    |
    */

    'supported_locales' => ['it', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Host attendibili
    |--------------------------------------------------------------------------
    |
    | Difesa contro l'host header injection: si accettano solo richieste il cui
    | Host compare in questa lista (i sottodomini sono inclusi). Se resta vuota
    | l'host viene dedotto da APP_URL.
    |
    | Va valorizzata quando il sito risponde su più domini — per esempio durante
    | la migrazione dal dominio di App Platform a quello definitivo, quando
    | entrambi devono restare raggiungibili. Elencare gli host senza schema,
    | separati da virgola.
    |
    */

    'trusted_hosts' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', '')))
    )),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
