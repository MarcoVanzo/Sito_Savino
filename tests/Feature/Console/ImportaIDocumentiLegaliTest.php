<?php

namespace Tests\Feature\Console;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I documenti di Corporate Governance erano spariti col passaggio a Spaces e
 * le impostazioni `legal.*` sono rimaste vuote. Il comando li riprende dal
 * sito precedente, che li pubblica ancora.
 */
class ImportaIDocumentiLegaliTest extends TestCase
{
    use RefreshDatabase;

    private const RAZZISMO = 'legal.protocollo_razzismo';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    #[Test]
    public function scarica_i_documenti_e_ne_salva_il_percorso(): void
    {
        Http::fake(['savinodelbenevolley.it/*' => Http::response('%PDF-1.4 finto')]);

        $this->artisan('documenti:importa-dal-vecchio-sito')->assertSuccessful();

        $percorso = SiteSetting::get(self::RAZZISMO);

        $this->assertSame('legal/Protocollo-3-Razzismo-e-xenofobia.pdf', $percorso);
        Storage::disk()->assertExists($percorso);
    }

    #[Test]
    public function non_riscarica_un_documento_gia_presente(): void
    {
        Storage::disk()->put('legal/mio.pdf', '%PDF-1.4 caricato dal pannello');
        SiteSetting::set(self::RAZZISMO, 'legal/mio.pdf');

        Http::fake(['savinodelbenevolley.it/*' => Http::response('%PDF-1.4 finto')]);

        $this->artisan('documenti:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame('legal/mio.pdf', SiteSetting::get(self::RAZZISMO));

        // Gli altri quattro mancano ancora, quindi qualche richiesta parte:
        // quella che non deve partire è verso il protocollo razzismo.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'Razzismo'));
    }

    #[Test]
    public function non_salva_una_pagina_di_errore_al_posto_del_pdf(): void
    {
        // Il sito di origine è un WordPress: una risorsa sparita può tornare
        // con 200 e una pagina HTML. Salvarla darebbe un documento illeggibile
        // in archivio, senza che nessun errore lo segnali.
        Http::fake(['savinodelbenevolley.it/*' => Http::response('<html>Pagina non trovata</html>')]);

        $this->artisan('documenti:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertEmpty(SiteSetting::get(self::RAZZISMO));
    }

    #[Test]
    public function un_documento_irraggiungibile_non_ferma_gli_altri(): void
    {
        Http::fake([
            '*Razzismo*' => Http::response('non disponibile', 404),
            'savinodelbenevolley.it/*' => Http::response('%PDF-1.4 finto'),
        ]);

        $this->artisan('documenti:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertEmpty(SiteSetting::get(self::RAZZISMO));
        $this->assertSame(
            'legal/Protocollo-2-Bullismo-e-cyberbullismo.pdf',
            SiteSetting::get('legal.protocollo_bullismo')
        );
    }
}
