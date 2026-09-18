<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La protezione dell'anteprima si accende da `PREVIEW_AUTH_ENABLED`, e quando è
 * accesa senza credenziali tiene il sito chiuso invece di aprirlo in silenzio.
 */
class PreviewBasicAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_protezione_spenta_il_sito_e_pubblico(): void
    {
        config(['services.preview.enabled' => false]);

        $this->get('/')->assertOk();
    }

    public function test_protezione_spenta_anche_con_credenziali_impostate(): void
    {
        // Le credenziali restano nello spec di App Platform anche ora che il
        // sito è pubblico: da sole non devono più chiudere niente.
        config([
            'services.preview.enabled' => false,
            'services.preview.user' => 'anteprima',
            'services.preview.pass' => 'segreta',
        ]);

        $this->get('/')->assertOk();
    }

    public function test_protezione_accesa_senza_credenziali_chiude_il_sito(): void
    {
        config([
            'services.preview.enabled' => true,
            'services.preview.user' => null,
            'services.preview.pass' => null,
        ]);

        $this->get('/')->assertStatus(503);
    }

    public function test_protezione_accesa_rifiuta_chi_non_ha_credenziali(): void
    {
        config([
            'services.preview.enabled' => true,
            'services.preview.user' => 'anteprima',
            'services.preview.pass' => 'segreta',
        ]);

        $this->get('/')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic');
    }

    public function test_protezione_accesa_rifiuta_la_password_sbagliata(): void
    {
        config([
            'services.preview.enabled' => true,
            'services.preview.user' => 'anteprima',
            'services.preview.pass' => 'segreta',
        ]);

        $this->withBasicAuth('anteprima', 'sbagliata')->get('/')->assertStatus(401);
    }

    public function test_protezione_accesa_lascia_passare_le_credenziali_giuste(): void
    {
        config([
            'services.preview.enabled' => true,
            'services.preview.user' => 'anteprima',
            'services.preview.pass' => 'segreta',
        ]);

        $this->withBasicAuth('anteprima', 'segreta')->get('/')->assertOk();
    }
}
