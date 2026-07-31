<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * In produzione le immagini del mega-menu possono venire dalla media library,
 * non dai file statici: `getFirstMediaUrl('menu-images')` ha la precedenza.
 * Lì stavano i JPEG originali del primo seed — 26 MB in tutto, con
 * ticketing.jpg da 9,5 MB — serviti in un riquadro largo circa 290 pixel.
 *
 * `app:seed-menu-images` carica i WebP a 720px (300 KB in tutto), e questa
 * migrazione lo esegue al deploy per non doversene ricordare a mano.
 *
 * Il seed scrive su Spaces, quindi dipende da un servizio esterno: al primo
 * tentativo è fallito con `InvalidAccessKeyId` perché le credenziali S3 di
 * produzione non erano valide. Un'operazione di contenuto non deve però far
 * cadere `migrate --force`, che in `start.sh` gira sotto `set -e`: bloccarlo
 * significa fermare l'avvio del container e, soprattutto, impedire che le
 * migrazioni successive — quelle di schema — vengano mai applicate. Qui
 * l'errore viene registrato e la migrazione prosegue: senza le immagini in
 * media library il mega-menu ricade sui file statici, che sono i medesimi
 * WebP leggeri.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            Artisan::call('app:seed-menu-images');
        } catch (Throwable $e) {
            Log::error('Reseed delle immagini di menu non riuscito: il mega-menu resta sui file statici.', [
                'message' => $e->getMessage(),
            ]);
        }
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
