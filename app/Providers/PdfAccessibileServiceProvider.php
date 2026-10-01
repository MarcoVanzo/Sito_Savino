<?php

namespace App\Providers;

use App\Support\Accessibilita\PdfAccessibile;
use Illuminate\Support\ServiceProvider;

/**
 * Sostituisce il wrapper di laravel-dompdf con PdfAccessibile (titolo mostrato
 * e lingua del documento). In `boot()` e non in `register()`: il provider del
 * pacchetto registra la sua versione, e questa deve arrivare dopo.
 *
 * Qui si indica anche la cartella dei caratteri: `resources/fonts/pdf` contiene
 * Montserrat già installato per dompdf (TTF, metriche `.ufm` e catalogo
 * `installed-fonts.json`, con i nomi dei file relativi). Il predefinito del
 * pacchetto è `storage/fonts`, che nel container non esiste: un carattere
 * dichiarato con `@font-face` verrebbe installato lì al primo PDF, cioè una
 * scrittura in produzione a ogni avvio, e un errore se la cartella manca.
 * Così dompdf legge soltanto. I caratteri di serie (Helvetica, DejaVu) restano
 * quelli della libreria.
 */
class PdfAccessibileServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        config([
            'dompdf.options.font_dir' => resource_path('fonts/pdf'),
            'dompdf.options.font_cache' => resource_path('fonts/pdf'),
            // Nel file solo i glifi usati, non i due caratteri interi (130 KB l'uno).
            'dompdf.options.enable_font_subsetting' => true,
        ]);

        $this->app->bind('dompdf.wrapper', fn ($app) => new PdfAccessibile($app['dompdf'], $app['config'], $app['files'], $app['view']));
    }
}
