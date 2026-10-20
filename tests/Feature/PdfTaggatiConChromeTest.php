<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ReceiptService;
use App\Support\Accessibilita\PdfConChrome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ricevuta e condizioni allegate alla conferma escono come PDF taggati
 * (StructTreeRoot), con lingua e titolo, quando c'è Chrome (PdfConChrome).
 * Su GitHub Actions Chrome è installato nel runner; in locale si salta solo
 * se manca davvero.
 */
class PdfTaggatiConChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (PdfConChrome::binario() === null) {
            $this->markTestSkipped('Chrome non è installato: i PDF li fa dompdf, senza tag.');
        }
    }

    private function assertPdfTaggato(string $pdf, string $lingua): void
    {
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/StructTreeRoot', $pdf);
        $this->assertMatchesRegularExpression('/\/MarkInfo\s*<<[^>]*\/Marked\s+true/', $pdf);
        $this->assertStringContainsString('/Lang ('.$lingua.')', $pdf);
        $this->assertMatchesRegularExpression('/\/Title\s*[(<]/', $pdf);
        $this->assertMatchesRegularExpression('/\/DisplayDocTitle\s+true/', $pdf);
    }

    #[Test]
    public function le_condizioni_allegate_alla_conferma_sono_taggate_con_lingua_e_titolo(): void
    {
        $ordine = Order::factory()->create(['locale' => 'en']);

        $pdf = (new OrderConfirmation($ordine))->attachments()[0]->attachWith(fn ($percorso) => null, fn ($dati) => $dati());

        $this->assertPdfTaggato($pdf, 'en');
        $this->assertMatchesRegularExpression('/\/S\s*\/H[1-3]\b/', $pdf);
    }

    #[Test]
    public function la_ricevuta_e_taggata_con_titolo_e_tabella_degli_articoli(): void
    {
        $ordine = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $ordine->id]);

        $pdf = app(ReceiptService::class)->generate($ordine);

        $this->assertPdfTaggato($pdf, 'it');
        $this->assertMatchesRegularExpression('/\/S\s*\/H1\b/', $pdf);
        $this->assertMatchesRegularExpression('/\/S\s*\/Table\b/', $pdf);
        $this->assertMatchesRegularExpression('/\/S\s*\/TH\b/', $pdf);
    }

    #[Test]
    public function spento_chrome_resta_il_pdf_di_dompdf(): void
    {
        config(['services.chrome.abilitato' => false]);

        $pdf = app(ReceiptService::class)->generate(Order::factory()->create());

        $this->assertStringNotContainsString('/StructTreeRoot', $pdf);
        $this->assertStringContainsString('/Lang (it)', $pdf);
    }
}
