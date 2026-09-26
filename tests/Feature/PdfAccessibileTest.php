<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Support\Accessibilita\PdfAccessibile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I PDF che generiamo dichiarano lingua e titolo (WCAG 3.1.1, 2.4.2): senza
 * `/Lang` uno screen reader li legge con la voce della lingua di sistema,
 * senza `DisplayDocTitle` il lettore mostra il nome del file. dompdf non
 * scrive la lingua: la aggiunge PdfAccessibile con un aggiornamento
 * incrementale, che deve lasciare un file valido.
 */
class PdfAccessibileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function l_allegato_delle_condizioni_dichiara_lingua_e_titolo(): void
    {
        $ordine = Order::factory()->create(['locale' => 'en']);

        $pdf = (new OrderConfirmation($ordine))->attachments()[0]->attachWith(fn ($percorso) => null, fn ($dati) => $dati());

        $this->assertStringContainsString('/Lang (en)', $pdf);
        $this->assertStringContainsString('/DisplayDocTitle true', $pdf);
        $this->assertMatchesRegularExpression('/\/Title \(/', $pdf);
        // L'ultimo startxref punta alla tabella nuova, che rimanda alla vecchia.
        $this->assertSame(1, preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $m));
        $this->assertSame('xref', substr($pdf, (int) $m[1], 4));
        $this->assertStringContainsString('/Prev ', substr($pdf, (int) $m[1]));
    }

    #[Test]
    public function la_lingua_viene_da_html_lang_e_si_aggiunge_una_volta_sola(): void
    {
        $pdf = Pdf::loadHTML('<html lang="it"><head><title>Prova</title></head><body>x</body></html>')->output();

        $this->assertSame(1, substr_count($pdf, '/Lang (it)'));
        $this->assertSame($pdf, PdfAccessibile::conLingua($pdf, 'it'));
    }

    #[Test]
    public function senza_lang_il_pdf_resta_quello_di_dompdf(): void
    {
        $pdf = Pdf::loadHTML('<html><body>x</body></html>')->output();

        $this->assertStringNotContainsString('/Lang', $pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
