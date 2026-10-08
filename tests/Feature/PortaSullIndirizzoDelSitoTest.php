<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;

/**
 * La navigazione su `www.` e sull'indirizzo di App Platform si sposta sul
 * dominio; webhook, API e /up restano dove sono.
 */
class PortaSullIndirizzoDelSitoTest extends TestCase
{
    use RefreshDatabase;

    private const APP_PLATFORM = 'https://seashell-app-47mmf.ondigitalocean.app';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Gli host fidati di Symfony sono statici: un test precedente che
        // simula la produzione li lascia impostati e qui darebbero 400.
        Request::setTrustedHosts([]);
        config(['app.url' => 'https://savinodelbenevolley.it']);
    }

    #[Test]
    public function app_platform_porta_al_dominio_con_percorso_e_query(): void
    {
        $this->get(self::APP_PLATFORM.'/news?page=2')
            ->assertStatus(301)
            ->assertRedirect('https://savinodelbenevolley.it/news?page=2');
    }

    #[Test]
    public function www_porta_al_dominio(): void
    {
        $this->get('https://www.savinodelbenevolley.it/en/news')
            ->assertStatus(301)
            ->assertRedirect('https://savinodelbenevolley.it/en/news');
    }

    #[Test]
    public function anche_il_pannello_si_sposta(): void
    {
        $this->get(self::APP_PLATFORM.'/admin/login')
            ->assertStatus(301)
            ->assertRedirect('https://savinodelbenevolley.it/admin/login');
    }

    #[Test]
    public function una_visita_inertia_naviga_per_intero_invece_di_seguire_il_301(): void
    {
        $this->get(self::APP_PLATFORM.'/news', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://savinodelbenevolley.it/news');
    }

    #[Test]
    public function il_dominio_non_si_sposta(): void
    {
        $this->get('https://savinodelbenevolley.it/')->assertOk();
    }

    #[Test]
    public function up_e_api_restano_sull_indirizzo_di_app_platform(): void
    {
        $this->get(self::APP_PLATFORM.'/up')->assertOk();
        $this->get(self::APP_PLATFORM.'/api/webhooks/stripe')->assertStatus(405);
    }

    #[Test]
    public function le_post_dei_webhook_non_si_spostano(): void
    {
        $risposta = $this->post(self::APP_PLATFORM.'/api/webhooks/resend', []);

        $this->assertNotSame(301, $risposta->getStatusCode());
        $this->assertNotSame(409, $risposta->getStatusCode());
    }

    #[Test]
    public function con_app_url_su_app_platform_non_rimbalza_su_se_stesso(): void
    {
        config(['app.url' => self::APP_PLATFORM]);

        $this->get(self::APP_PLATFORM.'/')->assertOk();
    }
}
