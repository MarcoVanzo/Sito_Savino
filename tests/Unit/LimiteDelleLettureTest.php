<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il limite `web` e' per indirizzo, e dietro lo stesso indirizzo stanno il
 * Wi-Fi del palazzetto e le reti mobili: le letture hanno un tetto largo, le
 * scritture restano strette e su un contatore diverso.
 */
class LimiteDelleLettureTest extends TestCase
{
    #[Test]
    public function le_letture_anonime_hanno_un_tetto_piu_largo_delle_scritture(): void
    {
        $limiter = RateLimiter::limiter('web');

        $lettura = $limiter(Request::create('/news', 'GET', server: ['REMOTE_ADDR' => '203.0.113.7']));
        $scrittura = $limiter(Request::create('/contatti', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7']));

        $this->assertSame(300, $lettura->maxAttempts);
        $this->assertSame(60, $scrittura->maxAttempts);
        $this->assertNotSame($lettura->key, $scrittura->key);
    }
}
