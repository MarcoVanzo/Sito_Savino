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
        try {
            $this->assertSame(0, Artisan::call('route:cache'));
        } finally {
            Artisan::call('route:clear');
        }
    }
}
