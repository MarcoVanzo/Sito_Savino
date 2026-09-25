<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\InformativeDaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Informativa promozionale e fornitori erano due PDF in Documenti Legali: ora
 * sono pagine come le altre voci legali del footer.
 */
class InformativeDaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_due_informative_nascono_come_pagine_pubblicate_in_entrambe_le_lingue(): void
    {
        foreach ([InformativeDaDocumento::PROMOZIONALE, InformativeDaDocumento::FORNITORI] as $slug) {
            $pagina = Page::where('slug', $slug)->first();

            $this->assertNotNull($pagina, "Manca la pagina {$slug}");
            $this->assertSame('Public/ContentPage', $pagina->template);
            $this->assertStringContainsString('privacy@savinodelbenevolley.it', $pagina->getTranslation('content', 'it', false));
            $this->assertNotEmpty($pagina->getTranslation('content', 'en', false));

            $this->get("/{$slug}")->assertOk();
        }
    }

    #[Test]
    public function il_titolare_e_la_ragione_sociale_per_esteso_e_gli_indirizzi_sono_quelli_del_sito(): void
    {
        foreach (InformativeDaDocumento::tutte() as $pagina) {
            foreach ($pagina['contenuto'] as $testo) {
                $this->assertStringContainsString('Società Sportiva Dilettantistica a Responsabilità Limitata', $testo);
                $this->assertStringNotContainsString('savinodelbene.com', $testo);
                $this->assertStringNotContainsString('ssdrl', $testo);
                $this->assertStringNotContainsString('Firma', $testo);
            }
        }
    }

    #[Test]
    public function una_pagina_gia_esistente_non_viene_riscritta(): void
    {
        Page::where('slug', InformativeDaDocumento::FORNITORI)->first()
            ->setTranslation('content', 'it', '<p>Scritto dalla redazione</p>')->save();

        $this->assertSame([], InformativeDaDocumento::creaQuelleCheMancano());
        $this->assertSame(
            '<p>Scritto dalla redazione</p>',
            Page::where('slug', InformativeDaDocumento::FORNITORI)->first()->getTranslation('content', 'it'),
        );
    }
}
