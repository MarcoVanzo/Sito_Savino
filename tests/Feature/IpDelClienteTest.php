<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'IP del visitatore decide i limiti per IP (login, reset, recesso,
 * newsletter, diagnostica). Con i proxy fidati `*` Laravel lo leggeva da
 * X-Forwarded-For, che chiunque può scrivere: bastava cambiarlo a ogni
 * richiesta per non incontrare mai il limite. Su App Platform l'IP vero lo
 * porta `DO-Connecting-IP`, scritto dal bordo di DigitalOcean.
 */
class IpDelClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->app['router']->get('/api/_test/ip-del-cliente', fn () => response(request()->ip()));
    }

    #[Test]
    public function l_ip_arriva_da_do_connecting_ip_e_non_da_x_forwarded_for(): void
    {
        $this->get('/api/_test/ip-del-cliente', [
            'DO-Connecting-IP' => '203.0.113.10',
            'X-Forwarded-For' => '198.51.100.99',
        ])->assertSeeText('203.0.113.10');
    }

    #[Test]
    public function senza_l_header_di_digitalocean_resta_il_ripiego_su_x_forwarded_for(): void
    {
        // Se App Platform smettesse di mandare DO-Connecting-IP, tutti i
        // visitatori finirebbero sull'IP del proxy e sullo stesso contatore dei
        // limiti: meglio il comportamento di prima.
        $this->get('/api/_test/ip-del-cliente', ['X-Forwarded-For' => '198.51.100.99'])
            ->assertSeeText('198.51.100.99');
    }

    #[Test]
    public function un_do_connecting_ip_non_valido_viene_ignorato(): void
    {
        $this->get('/api/_test/ip-del-cliente', ['DO-Connecting-IP' => 'non-un-ip'])
            ->assertSeeText('127.0.0.1');
    }

    #[Test]
    public function cambiare_x_forwarded_for_non_aggira_il_limite_del_login(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => "chi{$i}@example.test", 'password' => 'sbagliata'], [
                'DO-Connecting-IP' => '203.0.113.20',
                'X-Forwarded-For' => "198.51.100.{$i}",
            ])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'chi6@example.test', 'password' => 'sbagliata'], [
            'DO-Connecting-IP' => '203.0.113.20',
            'X-Forwarded-For' => '198.51.100.6',
        ])->assertStatus(429);
    }
}
