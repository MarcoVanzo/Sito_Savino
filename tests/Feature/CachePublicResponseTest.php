<?php

namespace Tests\Feature;

use App\Http\Middleware\CachePublicResponse;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

class CachePublicResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Svuotare la cache di pagina era una query per ogni indirizzo in cache,
     * presi da un registro che ogni pagina nuova riscriveva (Sentry
     * SITO-SAVINO-Z): adesso cambia la generazione nel nome delle chiavi.
     */
    public function test_un_contenuto_salvato_butta_le_pagine_in_cache(): void
    {
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

        Roster::factory()->create();

        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');
    }

    public function test_svuotare_la_cache_di_pagina_non_tiene_un_registro_degli_indirizzi(): void
    {
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/en')->assertOk();

        $this->assertNull(Cache::get(CachePublicResponse::CACHE_PREFIX.'registry'));

        CachePublicResponse::flush();

        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
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

    /**
     * Il cookie «ricordami» autentica la richiesta dentro la pipeline, dopo
     * che la cache ha già deciso: senza cookie di sessione la pagina con
     * `auth.user` veniva salvata e servita a tutti i visitatori anonimi.
     */
    public function test_con_il_cookie_ricordami_la_pagina_non_passa_dalla_cache(): void
    {
        $user = User::factory()->create(['email' => 'ricordata@example.test']);
        [$nome, $valore] = $this->cookieRicordami($user);

        $this->withCookie($nome, $valore)
            ->get('/')
            ->assertOk()
            ->assertHeaderMissing('X-Page-Cache');

        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();

        $anonimo = $this->get('/')->assertOk();
        $anonimo->assertHeader('X-Page-Cache', 'MISS');
        $this->assertStringNotContainsString('ricordata@example.test', $anonimo->getContent());
    }

    /**
     * Seconda difesa: anche se un giorno una richiesta autenticata superasse
     * il controllo dei cookie (un guard nuovo, un nome di cookie cambiato),
     * dopo la pipeline una risposta con utente autenticato non si salva.
     */
    public function test_una_risposta_con_utente_autenticato_non_viene_mai_salvata(): void
    {
        $user = User::factory()->create(['email' => 'dentro@example.test']);

        // Un middleware applicato dopo la cache autentica la richiesta senza
        // passare da nessun cookie riconosciuto.
        $this->app['router']->pushMiddlewareToGroup('web', AutenticaSenzaCookie::class);
        AutenticaSenzaCookie::$utente = $user;

        $this->get('/')->assertOk()->assertHeaderMissing('X-Page-Cache');

        AutenticaSenzaCookie::$utente = null;
        $this->app['auth']->forgetGuards();

        $anonimo = $this->get('/')->assertOk();
        $anonimo->assertHeader('X-Page-Cache', 'MISS');
        $this->assertStringNotContainsString('dentro@example.test', $anonimo->getContent());
    }

    /**
     * Il cookie che il guard `web` emetterebbe con «ricordami» spuntato.
     *
     * @return array{0: string, 1: string}
     */
    private function cookieRicordami(User $user): array
    {
        $guard = Auth::guard('web');
        $guard->login($user, true);

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn ($c) => $c->getName() === $guard->getRecallerName());
        $this->assertNotNull($cookie);

        // Resta solo il cookie: la sessione aperta da login() non deve
        // autenticare le richieste successive.
        Cookie::flushQueuedCookies();
        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();

        return [$cookie->getName(), $cookie->getValue()];
    }
}

/**
 * Solo per i test: autentica la richiesta senza cookie, come farebbe un guard
 * che la cache non conosce.
 */
class AutenticaSenzaCookie
{
    public static ?User $utente = null;

    public function handle(Request $request, \Closure $next): mixed
    {
        if (self::$utente !== null) {
            Auth::guard('web')->setUser(self::$utente);
        }

        return $next($request);
    }
}
