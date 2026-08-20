<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_response_has_x_content_type_options_header(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_response_has_x_frame_options_header(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_response_has_referrer_policy_header(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_response_has_permissions_policy_header(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    /**
     * Finché il dominio ufficiale serve il sito precedente, l'indirizzo di
     * anteprima non deve finire su Google: sarebbero due copie dello stesso
     * contenuto, e la copia sbagliata potrebbe pure vincere.
     */
    public function test_l_indirizzo_di_anteprima_non_viene_indicizzato(): void
    {
        config()->set('app.indexable_hosts', ['savinodelbenevolley.it']);

        $this->get('http://seashell-app-47mmf.ondigitalocean.app/')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_il_dominio_definitivo_resta_indicizzabile(): void
    {
        config()->set('app.indexable_hosts', ['savinodelbenevolley.it', 'www.savinodelbenevolley.it']);

        $this->get('http://www.savinodelbenevolley.it/')
            ->assertHeaderMissing('X-Robots-Tag');
    }

    /**
     * Elenco vuoto: nessun vincolo. Serve a poter spegnere il controllo da
     * variabile d'ambiente senza rilasciare codice.
     */
    public function test_senza_elenco_non_si_blocca_niente(): void
    {
        config()->set('app.indexable_hosts', []);

        $this->get('http://seashell-app-47mmf.ondigitalocean.app/')
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_response_has_csp_header(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("frame-src 'self' https://www.youtube.com", $csp);
    }
}
