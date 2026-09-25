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
    public function la_pagina_risponde(): void
    {
        $this->withoutVite();

        $this->get('/dichiarazione-di-accessibilita')->assertOk();
        $this->get('/en/dichiarazione-di-accessibilita')->assertOk();
    }
}
