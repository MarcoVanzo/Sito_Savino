<?php

namespace App\Providers;

use App\Support\Accessibilita\PdfAccessibile;
use Illuminate\Support\ServiceProvider;

/**
 * Sostituisce il wrapper di laravel-dompdf con PdfAccessibile (titolo mostrato
 * e lingua del documento). In `boot()` e non in `register()`: il provider del
 * pacchetto registra la sua versione, e questa deve arrivare dopo.
 */
class PdfAccessibileServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->bind('dompdf.wrapper', fn ($app) => new PdfAccessibile($app['dompdf'], $app['config'], $app['files'], $app['view']));
    }
}
