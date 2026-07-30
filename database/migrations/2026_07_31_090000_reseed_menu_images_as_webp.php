<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * In produzione le immagini del mega-menu vengono dalla media library, non dai
 * file statici: `getFirstMediaUrl('menu-images')` ha la precedenza. Lì stanno
 * ancora i JPEG originali del primo seed — 26 MB in tutto, con ticketing.jpg
 * da 9,5 MB — serviti in un riquadro largo circa 290 pixel.
 *
 * `app:seed-menu-images` ora carica i WebP a 720px (300 KB in tutto), ma
 * finché non lo si rilancia la media library resta quella di prima: senza
 * questa migrazione servirebbe ricordarsi di eseguirlo a mano a ogni deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Artisan::call('app:seed-menu-images');
    }

    /**
     * Non reversibile: gli originali non sono più nel repository e ricaricarli
     * significherebbe comunque rimettere in produzione le immagini pesanti.
     */
    public function down(): void
    {
        // no-op
    }
};
