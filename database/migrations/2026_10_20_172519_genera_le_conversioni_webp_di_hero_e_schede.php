<?php

use App\Models\HeroSlide;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Le conversioni WebP nuove (`hero_mobile` delle slide, `scheda` e
 * `scheda_media` dei prodotti) nascono da sole per le foto caricate da oggi;
 * per quelle gia' caricate si accodano qui, cosi' le genera il worker e non
 * l'avvio del container (le migrazioni girano a ogni deploy). Finche' non ci
 * sono, le pagine usano le misure di prima (HeroSlide::immaginiDellHero,
 * ShopController::fotoDellaScheda).
 *
 * Un guasto della coda non deve fermare il deploy: si registra e basta, il
 * comando si puo' rilanciare a mano dalla console.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lavori = [
            HeroSlide::class => ['hero_mobile'],
            Product::class => ['scheda', 'scheda_media'],
        ];

        foreach ($lavori as $modello => $conversioni) {
            try {
                Artisan::queue('media-library:regenerate', [
                    'modelType' => $modello,
                    '--only' => $conversioni,
                    '--only-missing' => true,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                Log::warning('Conversioni WebP non accodate', ['modello' => $modello, 'errore' => $e->getMessage()]);
            }
        }
    }

    /** Nessun ritorno: le conversioni in piu' non danno fastidio a nessuno. */
    public function down(): void {}
};
