<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Senza SSR le immagini LCP le scopriva solo il JavaScript, dopo il bundle:
 * l'HTML servito le precarica (audit SEO/ADV del 20/10/2026).
 */
class ImmaginiPrecaricateNellHtmlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function ogni_pagina_precarica_il_logo_volley_in_webp(): void
    {
        $html = $this->get('/shop')->getContent();

        $this->assertStringContainsString('<link rel="preload" as="image" href="/images/logo-volley-256.webp"', $html);
        $this->assertStringContainsString('imagesrcset="/images/logo-volley-128.webp 128w', $html);
    }

    #[Test]
    public function con_un_logo_della_redazione_la_serie_del_volley_non_si_precarica(): void
    {
        SiteSetting::set('site_logo', 'loghi/logo-nuovo.png', 'general');

        $html = $this->get('/shop')->getContent();

        $this->assertStringNotContainsString('logo-volley-256.webp', $html);
    }

    #[Test]
    public function la_home_precarica_la_prima_slide(): void
    {
        HeroSlide::factory()->create(['is_active' => true, 'sort_order' => 1]);

        $html = $this->get('/')->getContent();

        // Senza foto caricata la slide ripiega sull'immagine di riserva.
        $this->assertStringContainsString('<link rel="preload" as="image" href="/images/hero1.jpg"', $html);
        $this->assertStringContainsString('imagesizes="100vw"', $html);
    }

    #[Test]
    public function la_scheda_precarica_la_sua_prima_foto(): void
    {
        $prodotto = Product::factory()->create(['slug' => 'sciarpa', 'is_active' => true, 'stock' => 3]);
        $prodotto->addMedia(base_path('public/images/logo-volley-256.webp'))->preservingOriginal()->toMediaCollection('products');

        $risposta = $this->get(route('shop.product', $prodotto->slug));
        $foto = $risposta->viewData('page')['props']['product']['immagini'][0];

        $this->assertStringContainsString('<link rel="preload" as="image" href="'.e($foto['src']).'"', $risposta->getContent());
        $this->assertNotEmpty($foto['miniatura']);
    }
}
