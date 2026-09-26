<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La dichiarazione di accessibilita' (European Accessibility Act, D.Lgs.
 * 82/2022, Allegato V): esiste, risponde in entrambe le lingue e contiene
 * le parti che la norma chiede.
 */
class DichiarazioneDiAccessibilitaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_dichiarazione_nasce_con_le_migrazioni_e_dice_quello_che_serve(): void
    {
        $pagina = Page::where('slug', 'dichiarazione-di-accessibilita')->first();

        $this->assertNotNull($pagina);

        $testo = $pagina->getTranslation('content', 'it');

        $this->assertStringContainsString('EN 301 549', $testo);
        $this->assertStringContainsString('parzialmente conforme', $testo);
        $this->assertStringContainsString('info@savinodelbenevolley.it', $testo);
        $this->assertStringContainsString('AgID', $testo);
        $this->assertNotEmpty($pagina->getTranslation('content', 'en', false));
    }

    #[Test]
    public function dopo_le_migrazioni_i_limiti_sono_quelli_di_oggi(): void
    {
        $testo = Page::where('slug', 'dichiarazione-di-accessibilita')->first()->getTranslation('content', 'it');

        // Il checkout ora lo percorre la scansione: il limite e' caduto.
        $this->assertStringNotContainsString('non raggiunge le pagine che richiedono un carrello pieno', $testo);
        $this->assertStringContainsString('Modello organizzativo', $testo);
        $this->assertStringContainsString('screen reader simulato', $testo);
        // Le notizie dell'archivio non sono piu' un limite: nessun impegno
        // lasciato alla redazione.
        $this->assertStringNotContainsString('a mano', $testo);
        $this->assertStringContainsString('hanno un testo alternativo che ne riporta il contenuto', $testo);
    }

    #[Test]
    public function il_testo_modificato_dalla_redazione_non_si_tocca(): void
    {
        $pagina = Page::where('slug', 'dichiarazione-di-accessibilita')->first();
        $pagina->setTranslation('content', 'it', '<p>Scritto dalla redazione</p>')->save();

        $migrazione = require database_path('migrations/2026_09_26_130000_dichiarazione_di_accessibilita_checkout_e_pdf.php');
        $migrazione->up();

        $this->assertSame('<p>Scritto dalla redazione</p>', $pagina->fresh()->getTranslation('content', 'it'));
    }

    #[Test]
    public function la_pagina_risponde(): void
    {
        $this->withoutVite();

        $this->get('/dichiarazione-di-accessibilita')->assertOk();
        $this->get('/en/dichiarazione-di-accessibilita')->assertOk();
    }
}
