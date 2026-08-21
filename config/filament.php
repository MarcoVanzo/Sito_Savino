<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | By uncommenting the Laravel Echo configuration, you may connect Filament
    | to any Pusher-compatible websockets server.
    |
    | This will allow your users to receive real-time notifications.
    |
    */

    'broadcasting' => [

        // 'echo' => [
        //     'broadcaster' => 'pusher',
        //     'key' => env('VITE_PUSHER_APP_KEY'),
        //     'cluster' => env('VITE_PUSHER_APP_CLUSTER'),
        //     'wsHost' => env('VITE_PUSHER_HOST'),
        //     'wsPort' => env('VITE_PUSHER_PORT'),
        //     'wssPort' => env('VITE_PUSHER_PORT'),
        //     'authEndpoint' => '/broadcasting/auth',
        //     'disableStats' => true,
        //     'encrypted' => true,
        //     'forceTLS' => true,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Il disco su cui il pannello salva i file caricati dalla redazione.
    |
    | Il valore di serie del pacchetto e' 'public', cioe' il disco locale del
    | container: NON segue FILESYSTEM_DISK. In produzione il container viene
    | ricreato a ogni rilascio e la cartella con dentro i file se ne va, mentre
    | in archivio resta il percorso. E' cosi' che i PDF di Corporate Governance
    | sono finiti a puntare a file che non esistono, che il logo caricato nelle
    | Cartelle Stampa spariva cambiando sezione e che il bilancio di
    | sostenibilita' non compariva mai online.
    |
    | Le foto passano invece dalla media library, che ha una sua impostazione
    | (MEDIA_DISK) gia' puntata su Spaces: per questo funzionavano solo quelle,
    | ed e' il motivo per cui il difetto e' rimasto nascosto a lungo.
    |
    | Ora il pannello segue il disco dell'applicazione. FILAMENT_FILESYSTEM_DISK
    | resta disponibile per forzarne un altro.
    |
    | In sviluppo FILESYSTEM_DISK vale 'local', che e' il disco privato di
    | Laravel (storage/app/private): i suoi indirizzi rispondono 403 e il file
    | caricato non si vedrebbe. Li' si usa 'public', che il collegamento
    | creato da storage:link serve normalmente.
    |
    */

    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK') ?: match (env('FILESYSTEM_DISK', 'public')) {
        'local', '', null => 'public',
        default => env('FILESYSTEM_DISK'),
    },

    /*
    |--------------------------------------------------------------------------
    | Assets Path
    |--------------------------------------------------------------------------
    |
    | This is the directory where Filament's assets will be published to. It
    | is relative to the `public` directory of your Laravel application.
    |
    | After changing the path, you should run `php artisan filament:assets`.
    |
    */

    'assets_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | This is the directory that Filament will use to store cache files that
    | are used to optimize the registration of components.
    |
    | After changing the path, you should run `php artisan filament:cache-components`.
    |
    */

    'cache_path' => base_path('bootstrap/cache/filament'),

    /*
    |--------------------------------------------------------------------------
    | Livewire Loading Delay
    |--------------------------------------------------------------------------
    |
    | This sets the delay before loading indicators appear.
    |
    | Setting this to 'none' makes indicators appear immediately, which can be
    | desirable for high-latency connections. Setting it to 'default' applies
    | Livewire's standard 200ms delay.
    |
    */

    'livewire_loading_delay' => 'default',

    /*
    |--------------------------------------------------------------------------
    | System Route Prefix
    |--------------------------------------------------------------------------
    |
    | This is the prefix used for the system routes that Filament registers,
    | such as the routes for downloading exports and failed import rows.
    |
    */

    'system_route_prefix' => 'filament',

];
