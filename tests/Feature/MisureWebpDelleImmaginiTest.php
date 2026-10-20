<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\ImmaginiDaPrecaricare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le misure WebP (hero_mobile, scheda, scheda_media) si usano appena il
 * worker le ha generate; prima le pagine restano sulle misure di sempre.
 */
class MisureWebpDelleImmaginiTest extends TestCase
{
    use RefreshDatabase;

    private function foto(): string
    {
        return base_path('public/images/logo-volley-256.webp');
    }

    #[Test]
    public function la_slide_usa_hero_mobile_solo_quando_e_generata(): void
    {
        $slide = HeroSlide::factory()->create();
        $media = $slide->addMedia($this->foto())->preservingOriginal()->toMediaCollection('hero-slides');
        // Con la coda `sync` le conversioni nascono subito: si riparte da una
        // foto di cui il worker non ha ancora fatto niente.
        DB::table('media')->where('id', $media->id)->update(['generated_conversions' => '[]']);
        $media->refresh();

        $prima = $slide->fresh()->immaginiDellHero();
        $this->assertNull($prima['mobile']);
        $this->assertSame($media->getUrl(), $prima['grande']);

        $media->markAsConversionGenerated('hero')->markAsConversionGenerated('hero_mobile')->save();
        $dopo = $slide->fresh()->immaginiDellHero();

        $this->assertSame($media->getUrl('hero'), $dopo['grande']);
        $this->assertSame($media->getUrl('hero_mobile'), $dopo['mobile']);
    }

    #[Test]
    public function senza_foto_la_slide_ripiega_sull_immagine_di_riserva(): void
    {
        $this->assertSame(['grande' => '/images/hero1.jpg', 'mobile' => null], HeroSlide::factory()->create()->immaginiDellHero());
    }

    #[Test]
    public function la_prima_slide_si_precarica_con_le_due_misure(): void
    {
        $precarica = ImmaginiDaPrecaricare::primaSlideDellHero([['image' => '/h.jpg', 'image_mobile' => '/m.webp']]);

        $this->assertSame([['href' => '/m.webp', 'srcset' => '/m.webp 1200w, /h.jpg 1920w', 'sizes' => '100vw']], $precarica);
    }

    #[Test]
    public function con_il_video_in_testa_la_slide_non_si_precarica(): void
    {
        SiteSetting::set('hero_video_url', 'https://esempio.it/video.mp4', 'home');

        $this->assertSame([], ImmaginiDaPrecaricare::primaSlideDellHero([['image' => '/h.jpg']]));
        $this->assertSame([], ImmaginiDaPrecaricare::primaSlideDellHero([]));
    }

    #[Test]
    public function un_immagine_senza_indirizzo_non_si_precarica(): void
    {
        SiteSetting::set('site_logo', 'loghi/nuovo.png', 'general');

        $this->assertSame([], ImmaginiDaPrecaricare::perLaPagina([['href' => '']]));
    }

    #[Test]
    public function la_scheda_usa_le_misure_webp_generate(): void
    {
        Cache::flush();
        $prodotto = Product::factory()->create(['slug' => 'sciarpa-webp', 'is_active' => true, 'stock' => 3]);
        $media = $prodotto->addMedia($this->foto())->preservingOriginal()->toMediaCollection('products');
        $media->markAsConversionGenerated('scheda')->markAsConversionGenerated('scheda_media')->markAsConversionGenerated('thumb')->save();

        $foto = $this->get(route('shop.product', $prodotto->slug))->viewData('page')['props']['product']['immagini'][0];

        $this->assertSame($media->getUrl('scheda'), $foto['src']);
        $this->assertSame($media->getUrl('scheda_media').' 800w, '.$media->getUrl('scheda').' 1200w', $foto['srcset']);
        $this->assertSame($media->getUrl('thumb'), $foto['miniatura']);
    }
}
