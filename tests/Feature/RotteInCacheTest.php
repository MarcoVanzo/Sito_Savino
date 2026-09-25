<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `start.sh` esegue `route:cache` a ogni avvio del container web, e se fallisce
 * il container esce: il 25/09/2026 un nome di rotta già usato da un pacchetto
 * (`resend.webhook`, di resend/resend-laravel) ha fatto fallire il rilascio,
 * con rollback automatico. Senza cache i nomi doppi non danno errore, quindi
 * nessun altro test se ne accorge.
 */
class RotteInCacheTest extends TestCase
{
    #[Test]
    public function le_rotte_si_mettono_in_cache(): void
    {
        // Il file va in una cartella temporanea, non in bootstrap/cache: il
        // working tree è condiviso fra più sessioni, e un'esecuzione
        // interrotta lascerebbe a tutte le rotte vecchie in cache.
        $percorso = sys_get_temp_dir().'/rotte-in-cache-'.getmypid().'.php';
        putenv('APP_ROUTES_CACHE='.$percorso);
        $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $percorso;

        try {
            $this->assertSame(0, Artisan::call('route:cache'));
            $this->assertFileExists($percorso);
        } finally {
            Artisan::call('route:clear');
            putenv('APP_ROUTES_CACHE');
            unset($_ENV['APP_ROUTES_CACHE'], $_SERVER['APP_ROUTES_CACHE']);
            @unlink($percorso);
        }
    }
}
