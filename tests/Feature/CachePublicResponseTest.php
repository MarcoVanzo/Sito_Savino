<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CachePublicResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_page_is_never_full_page_cached(): void
    {
        // La pagina di login deve restare dinamica: la cache full-page rimuove
        // gli header Set-Cookie / X-XSRF-TOKEN e romperebbe il CSRF (419) per chi
        // apre /login senza aver già un cookie XSRF-TOKEN.
        $this->get('/login')->assertOk()->assertHeaderMissing('X-Page-Cache');
        $this->get('/login')->assertOk()->assertHeaderMissing('X-Page-Cache');
    }

    public function test_login_page_sets_a_fresh_csrf_cookie(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertNotNull(
            $response->getCookie('XSRF-TOKEN', false),
            'La pagina di login deve impostare il cookie XSRF-TOKEN per il login Inertia.'
        );
    }

    public function test_authenticated_page_is_not_full_page_cached(): void
    {
        // Le pagine autenticate non devono essere messe in cache (rischio di
        // servirle ad altri utenti).
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertHeaderMissing('X-Page-Cache');
    }

    public function test_chi_arriva_da_una_pagina_in_cache_puo_chiedere_il_cookie_csrf(): void
    {
        // Le pagine servite dalla cache non portano Set-Cookie: il frontend
        // (resources/js/bootstrap.js) chiede il cookie qui prima di un invio,
        // altrimenti il primo modulo risponderebbe 419.
        $risposta = $this->get('/csrf-cookie');

        $risposta->assertNoContent();
        $risposta->assertHeaderMissing('X-Page-Cache');
        $this->assertNotNull($risposta->getCookie('XSRF-TOKEN', false));
        $this->get('/csrf-cookie')->assertHeaderMissing('X-Page-Cache');
    }
}
