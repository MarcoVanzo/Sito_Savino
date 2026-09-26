<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Services\SitemapBuilder;
use App\Support\IndirizziPerLingua;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il selettore della lingua, gli hreflang e la sitemap aggiungevano o
 * toglievano `/en` al percorso: con gli slug tradotti `/contatti` diventava
 * `/en/contatti` (404, la rotta inglese è `/en/contacts`), e lo stesso per
 * recesso e shop. L'altra lingua ora si calcola dal nome della rotta.
 */
class IndirizziPerLinguaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function il_selettore_porta_contatti_su_contacts_e_ritorno(): void
    {
        $this->get('/contatti')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('alternateUrl', url('/en/contacts')));

        $this->get('/en/contacts')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('alternateUrl', url('/contatti')));
    }

    #[Test]
    public function il_selettore_traduce_lo_slug_del_recesso_e_tiene_la_query(): void
    {
        $this->get('/recesso?ordine=SDB-1')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('alternateUrl', url('/en/withdrawal').'?ordine=SDB-1'));
    }

    #[Test]
    public function gli_hreflang_dichiarano_gli_indirizzi_veri(): void
    {
        $html = $this->get('/contatti')->assertOk()->getContent();

        $this->assertStringContainsString('hreflang="en" href="'.url('/en/contacts').'"', $html);
        $this->assertStringContainsString('hreflang="it" href="'.url('/contatti').'"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/contatti').'"', $html);
        $this->assertStringNotContainsString('/en/contatti', $html);
    }

    #[Test]
    public function la_rotta_dello_shop_passa_i_parametri_nell_altra_lingua(): void
    {
        $this->assertSame(
            url('/en/shop/product/maglia-home'),
            IndirizziPerLingua::perRotta('shop.product', ['product' => 'maglia-home'], 'en'),
        );
        $this->assertSame(url('/shop/prodotto/maglia-home'), IndirizziPerLingua::perPercorso('/shop/prodotto/maglia-home', 'it'));
    }

    #[Test]
    public function i_defaults_della_rotta_non_finiscono_in_query(): void
    {
        // `/summer-camp` passa lo slug da defaults(): senza filtro route()
        // lo appendeva come `?slug=summer-camp`.
        $this->assertSame(url('/en/summer-camp'), IndirizziPerLingua::perPercorso('/summer-camp', 'en'));
    }

    #[Test]
    public function senza_la_rotta_nell_altra_lingua_si_ripiega_sulla_home(): void
    {
        Route::get('/solo-in-italiano', fn () => 'ok')->name('solo-in-italiano');
        Route::getRoutes()->refreshNameLookups();

        $this->assertNull(IndirizziPerLingua::perRotta('solo-in-italiano', [], 'en'));
        $this->assertSame(url('/en'), IndirizziPerLingua::home('en'));
    }

    #[Test]
    public function una_rotta_firmata_non_ha_alternato(): void
    {
        // Senza la firma risponderebbe 403.
        $this->assertNull(IndirizziPerLingua::perRotta('newsletter.conferma.show', ['subscriber' => 1], 'en'));
    }

    #[Test]
    public function la_sitemap_mette_contatti_su_contacts(): void
    {
        Page::updateOrCreate(['slug' => 'contatti'], [
            'title' => ['it' => 'Contatti', 'en' => 'Contacts'],
            'template' => 'Public/Contatti',
            'status' => PostStatus::Published,
        ]);

        $xml = app(SitemapBuilder::class)->build()->render();

        $this->assertStringContainsString('<loc>'.url('/en/contacts').'</loc>', $xml);
        $this->assertStringNotContainsString('/en/contatti', $xml);
    }

    #[Test]
    public function ogni_indirizzo_della_sitemap_risponde_200(): void
    {
        Page::updateOrCreate(['slug' => 'contatti'], [
            'title' => ['it' => 'Contatti', 'en' => 'Contacts'],
            'template' => 'Public/Contatti',
            'status' => PostStatus::Published,
        ]);

        $xml = app(SitemapBuilder::class)->build()->render();
        preg_match_all('#<loc>([^<]+)</loc>|href="([^"]+)"#', $xml, $trovati);
        $indirizzi = array_unique(array_filter(array_merge($trovati[1], $trovati[2])));

        $this->assertNotEmpty($indirizzi);

        $falliti = [];
        foreach ($indirizzi as $indirizzo) {
            $percorso = parse_url($indirizzo, PHP_URL_PATH) ?: '/';
            $stato = $this->get($percorso)->getStatusCode();

            if ($stato !== 200) {
                $falliti[] = "{$stato} {$percorso}";
            }
        }

        $this->assertSame([], $falliti, 'Indirizzi della sitemap che non rispondono 200');
    }

    #[Test]
    public function i_meta_statici_si_fanno_sostituire_da_quelli_della_pagina(): void
    {
        // Senza la chiave `inertia` il <Head> della pagina aggiungeva i suoi
        // meta accanto a questi, e nel DOM ce n'erano due di ciascuno.
        $html = $this->get('/contatti')->assertOk()->getContent();

        foreach (['description', 'og:title', 'og:description', 'og:url', 'og:image'] as $chiave) {
            $this->assertMatchesRegularExpression('#<meta [^>]*inertia="'.preg_quote($chiave, '#').'"#', $html, $chiave);
        }

        // og:url è la pagina, non APP_URL.
        $this->assertStringContainsString('property="og:url" content="'.url('/contatti').'"', $html);
    }
}
