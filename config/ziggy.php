<?php

/*
|--------------------------------------------------------------------------
| Ziggy: quali rotte arrivano al browser
|--------------------------------------------------------------------------
|
| `@routes` stampa in ogni pagina pubblica l'elenco delle rotte con nome e
| indirizzo. Senza filtro c'erano anche il pannello (/admin e le sue ~110
| rotte), Livewire, i webhook dei pagamenti e della posta, lo storage locale:
| una mappa gratuita di cosa attaccare, che al frontend non serve.
|
| Si escludono solo rotte che il frontend non chiama per nome: prima di
| aggiungere un pattern, `grep -rn "route('" resources/js` (compresi i nomi
| costruiti, come `routeName` in SeasonNav.vue). Il frontend chiama per
| indirizzo fisso, non per nome, /api/diagnostica, /csrf-cookie e lo stato
| delle aste: non passano da qui.
|
*/

return [
    'except' => [
        'filament.*',
        'livewire.*',
        'default.livewire.*',
        'admin.*',
        'api.*',
        'diagnostica',
        '*webhook*',
        'sanctum.*',
        'storage.*',
        'debugbar.*',
        'ignition.*',
        'horizon.*',
        'telescope.*',
        'pulse.*',
    ],
];
