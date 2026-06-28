<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync Lega Volley solo in ambienti non-production
if (! app()->isProduction()) {
    Schedule::command('sync:legavolley')->daily();
}

Schedule::command('sitemap:generate')->daily()->at('04:00');

// Pulizia periodica
Schedule::command('activity-log:prune --days=180 --force')->weekly();
Schedule::command('model:prune')->daily();
