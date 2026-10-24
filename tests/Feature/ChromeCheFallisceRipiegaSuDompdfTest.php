<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\ReceiptService;
use App\Support\Accessibilita\PdfConChrome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quando Chrome c'è ma non stampa, la ricevuta esce lo stesso, da dompdf:
 * un PDF senza tag è meglio di una conferma d'ordine senza allegato. Il
 * «Chrome» qui è uno script che fallisce, così il caso gira anche dove il
 * browser non è installato.
 */
class ChromeCheFallisceRipiegaSuDompdfTest extends TestCase
{
    use RefreshDatabase;

    private string $cartella;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartella = storage_path('framework/testing/chrome-finto');
        File::ensureDirectoryExists($this->cartella);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cartella);

        parent::tearDown();
    }

    #[Test]
    public function chrome_che_esce_con_errore_lascia_la_ricevuta_a_dompdf(): void
    {
        $binario = $this->cartella.'/chrome';
        File::put($binario, "#!/bin/sh\necho 'crash del renderer' >&2\nexit 1\n");
        chmod($binario, 0755);
        config(['services.chrome.path' => $binario]);
        Log::spy();

        $pdf = app(ReceiptService::class)->generate(Order::factory()->create());

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('/StructTreeRoot', $pdf);
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $messaggio, array $contesto) => str_contains($messaggio, 'ripiego su dompdf')
                && $contesto['uscita'] === 1
                && str_contains($contesto['errore'], 'crash del renderer'),
        );
    }

    #[Test]
    public function un_percorso_configurato_che_non_si_esegue_non_cerca_altrove(): void
    {
        $binario = $this->cartella.'/chrome';
        File::put($binario, '');
        chmod($binario, 0644);
        config(['services.chrome.path' => $binario]);

        $this->assertNull(PdfConChrome::binario());
    }
}
