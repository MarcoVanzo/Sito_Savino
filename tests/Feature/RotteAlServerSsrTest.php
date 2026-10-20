<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il server SSR non ha la direttiva @routes: le rotte di config/ziggy.php gli
 * arrivano nella prop `ziggy`, ma solo ai caricamenti interi e con l'SSR
 * acceso. Le visite Inertia non se le portano dietro a ogni pagina.
 */
class RotteAlServerSsrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Nessun server in ascolto: la pagina ripiega sul rendering nel browser.
        config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => 'http://127.0.0.1:9']);
    }

    #[Test]
    public function il_caricamento_intero_porta_le_rotte(): void
    {
        $ziggy = $this->get('/shop')->assertOk()->viewData('page')['props']['ziggy'];

        $this->assertArrayHasKey('home', $ziggy['routes']);
        $this->assertArrayNotHasKey('filament.admin.auth.login', $ziggy['routes']);
        $this->assertSame(url('/shop'), $ziggy['location']);
    }

    #[Test]
    public function la_visita_inertia_no(): void
    {
        $ziggy = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])
            ->get('/shop')
            ->json('props.ziggy');

        $this->assertArrayNotHasKey('routes', $ziggy);
    }

    #[Test]
    public function con_l_ssr_spento_neanche(): void
    {
        config(['inertia.ssr.enabled' => false]);

        $ziggy = $this->get('/shop')->viewData('page')['props']['ziggy'];

        $this->assertArrayNotHasKey('routes', $ziggy);
    }
}
