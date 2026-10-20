<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il 404 si presentava con il titolo generico del sito (audit SEO del
 * 20/10/2026): nell'HTML servito, prima del JavaScript, ha il suo.
 */
class TitoloDellaPaginaNonTrovataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function il_404_ha_il_titolo_pagina_non_trovata(): void
    {
        $html = $this->get('/indirizzo-che-non-esiste-davvero')->assertNotFound()->getContent();

        $this->assertStringContainsString('<title inertia>Pagina non trovata | Savino Del Bene Volley</title>', $html);
    }

    #[Test]
    public function sotto_en_il_titolo_e_in_inglese(): void
    {
        $html = $this->get('/en/an-address-that-does-not-exist')->assertNotFound()->getContent();

        $this->assertStringContainsString('<title inertia>Page not found | Savino Del Bene Volley</title>', $html);
    }
}
