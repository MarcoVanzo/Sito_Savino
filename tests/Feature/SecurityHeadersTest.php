<?php

namespace Tests\Feature;

use App\Support\LiveStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
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
     *
     * Le richieste di prova arrivano su `localhost`: si sposta l'elenco degli
     * host ammessi, non l'host della richiesta, perché gli host attendibili di
     * Symfony sono uno stato statico del processo e un test che ne cambia il
     * valore condiziona quelli successivi.
     */
    public function test_un_host_fuori_elenco_non_viene_indicizzato(): void
    {
        config()->set('app.indexable_hosts', ['savinodelbenevolley.it']);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_il_dominio_definitivo_resta_indicizzabile(): void
    {
        config()->set('app.indexable_hosts', ['localhost', 'www.savinodelbenevolley.it']);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    /**
     * Elenco vuoto: nessun vincolo. Serve a poter spegnere il controllo da
     * variabile d'ambiente senza rilasciare codice.
     */
    public function test_senza_elenco_non_si_blocca_niente(): void
    {
        config()->set('app.indexable_hosts', []);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_response_has_csp_header(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("frame-src 'self' https://www.google.com", $csp);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dirette(): array
    {
        return [
            'youtube' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'vimeo' => ['https://vimeo.com/123456789'],
            'twitch' => ['https://www.twitch.tv/legavolley'],
            'dailymotion' => ['https://www.dailymotion.com/video/x8abcd'],
        ];
    }

    /**
     * Le piattaforme che `LiveStream` sa incorporare devono essere ammesse
     * anche dalla CSP: un link accettato di là e vietato di qua supera i
     * controlli della redazione e poi resta un riquadro bianco, bloccato dal
     * browser senza che a schermo si capisca perché.
     */
    #[DataProvider('dirette')]
    public function test_le_dirette_incorporabili_sono_ammesse_dalla_csp(string $url): void
    {
        $embed = LiveStream::embedUrl($url);
        $this->assertNotNull($embed, 'la piattaforma dovrebbe essere incorporabile');

        $host = parse_url($embed, PHP_URL_SCHEME).'://'.parse_url($embed, PHP_URL_HOST);
        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $frameSrc = collect(explode('; ', (string) $csp))->first(fn (string $riga): bool => str_starts_with($riga, 'frame-src'));

        $this->assertStringContainsString($host, (string) $frameSrc);
    }

    /**
     * GA4 e il Pixel di Meta caricano il loro codice da un dominio altrui e
     * spediscono lì i dati raccolti. La policy li bloccava entrambi: tutto
     * configurato — misurazione ferma, e nessun errore da nessuna parte se non
     * nella console del browser.
     */
    public function test_la_policy_lascia_passare_le_due_misurazioni_del_sito(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://connect.facebook.net', $csp);

        // Caricare lo script senza poter spedire i dati non servirebbe a nulla.
        [$connectSrc] = array_values(array_filter(
            explode('; ', $csp),
            fn (string $direttiva) => str_starts_with($direttiva, 'connect-src'),
        ));

        $this->assertStringContainsString('https://www.google-analytics.com', $connectSrc);
        $this->assertStringContainsString('https://connect.facebook.net', $connectSrc);
    }

    /**
     * `'unsafe-inline'` su `script-src` vuol dire che qualunque `<script>`
     * finito nella pagina viene eseguito: con il contenuto del CMS che arriva
     * in `v-html`, era l'unica cosa fra un XSS e il browser del visitatore.
     * Il nonce cambia a ogni richiesta e chi inietta non lo conosce.
     */
    public function test_la_pagina_pubblica_usa_il_nonce_invece_di_unsafe_inline(): void
    {
        $scriptSrc = $this->scriptSrc($this->get('/'));

        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]+'/", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-eval'", $scriptSrc);
    }

    /**
     * Il nonce dichiarato nell'intestazione deve essere quello stampato sui tag,
     * altrimenti la pagina resta senza script: Ziggy pubblica le rotte in un
     * blocco in linea, e senza quelle il frontend non sa più costruire un URL.
     */
    public function test_il_nonce_dell_intestazione_e_quello_dei_tag_della_pagina(): void
    {
        $risposta = $this->get('/');

        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $this->scriptSrc($risposta), $trovato);
        $this->assertNotEmpty($trovato, 'nessun nonce nella policy');

        $risposta->assertSee('nonce="'.$trovato[1].'"', false);
    }

    /**
     * Su una pagina che non passa dalla cache il nonce è nuovo a ogni
     * richiesta: è ciò che lo rende inindovinabile.
     */
    public function test_ogni_richiesta_ha_il_suo_nonce(): void
    {
        // `/login` è esclusa da CachePublicResponse (le serve un cookie CSRF
        // fresco), quindi qui si vedono due risposte davvero distinte.
        $primo = $this->scriptSrc($this->get('/login'));
        $secondo = $this->scriptSrc($this->get('/login'));

        $this->assertNotSame($primo, $secondo);
    }

    /**
     * La cache di pagina salva l'HTML e le sue intestazioni nello stesso
     * blocco, quindi una risposta servita dalla cache ha il nonce della policy
     * uguale a quello dei tag: se i due si separassero, la pagina arriverebbe
     * al visitatore senza uno script.
     */
    public function test_la_pagina_servita_dalla_cache_resta_coerente_col_suo_nonce(): void
    {
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');

        $dallaCache = $this->get('/');
        $dallaCache->assertHeader('X-Page-Cache', 'HIT');

        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $this->scriptSrc($dallaCache), $trovato);
        $this->assertNotEmpty($trovato, 'nessun nonce nella policy della risposta in cache');

        $dallaCache->assertSee('nonce="'.$trovato[1].'"', false);
    }

    /**
     * Il pannello è Filament, cioè Alpine, che valuta le espressioni dei
     * template con `new Function`: senza `unsafe-eval` non si disegna. La
     * policy larga resta confinata lì.
     */
    public function test_il_pannello_tiene_la_policy_larga(): void
    {
        $scriptSrc = $this->scriptSrc($this->get('/admin/login'));

        $this->assertStringContainsString("'unsafe-eval'", $scriptSrc);
        $this->assertStringContainsString("'unsafe-inline'", $scriptSrc);
    }

    /**
     * Il pannello non passa dal gruppo `web` e rispondeva senza alcun header di
     * sicurezza: incorniciabile in un iframe altrui, cioè clickjacking su
     * ordini e prezzi.
     */
    public function test_il_pannello_non_e_incorniciabile(): void
    {
        $this->get('/admin/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    /**
     * Filament prende il font Outfit da fonts.bunny.net: se la policy non lo
     * ammette il pannello si disegna col font di ripiego.
     */
    public function test_il_pannello_puo_caricare_il_suo_font(): void
    {
        $csp = (string) $this->get('/admin/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
    }

    /**
     * Il sito pubblico invece non ne ha bisogno: i suoi font arrivano da Google.
     */
    public function test_il_sito_pubblico_non_apre_host_che_non_gli_servono(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('https://fonts.bunny.net', $csp);
    }

    private function scriptSrc(TestResponse $risposta): string
    {
        $csp = (string) $risposta->headers->get('Content-Security-Policy');

        return collect(explode('; ', $csp))
            ->first(fn (string $direttiva): bool => str_starts_with($direttiva, 'script-src')) ?? '';
    }

    /**
     * L'apertura vale per quei due host e basta: `default-src` resta chiuso e
     * nessuna direttiva concede il jolly.
     */
    public function test_la_policy_non_si_apre_a_chiunque(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString('script-src *', $csp);
        $this->assertStringNotContainsString("script-src 'unsafe-hashes'", $csp);
        $this->assertStringNotContainsString('connect-src *', $csp);
    }
}
