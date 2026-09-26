<?php

namespace Tests\Feature\Shop;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un cliente disattivato dal pannello con la sessione ancora aperta non deve
 * più vedere l'area riservata dello shop: EnsureUserIsActive lo disconnette.
 */
class ClienteDisattivatoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function disattivato(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => false])->save();

        return $user->refresh();
    }

    public function test_un_cliente_disattivato_non_apre_il_suo_account(): void
    {
        $this->actingAs($this->disattivato())
            ->get(route('shop.account'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_un_cliente_disattivato_non_scarica_i_suoi_dati_ne_vede_gli_ordini(): void
    {
        $this->actingAs($this->disattivato())->get(route('shop.account.export'))->assertRedirect(route('login'));
        $this->actingAs($this->disattivato())->get(route('shop.orders'))->assertRedirect(route('login'));
    }

    public function test_un_cliente_attivo_apre_il_suo_account(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('shop.account'))
            ->assertOk();
    }

    /**
     * Ogni rotta dello shop protetta da `auth` deve passare anche da
     * EnsureUserIsActive: vale per le rotte aggiunte in futuro.
     */
    public function test_nessuna_rotta_pubblica_con_auth_salta_il_controllo_dell_account_attivo(): void
    {
        $scoperte = [];

        foreach (app('router')->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $uri = $route->uri();

            if (! in_array('auth', $middleware, true) || str_starts_with($uri, 'admin')) {
                continue;
            }

            if (str_contains($uri, 'shop') && ! in_array(EnsureUserIsActive::class, $middleware, true)) {
                $scoperte[] = $uri;
            }
        }

        $this->assertSame([], $scoperte, 'Rotte dello shop senza EnsureUserIsActive: '.implode(', ', $scoperte));
    }
}
