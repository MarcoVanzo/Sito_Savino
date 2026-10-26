<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/en/news/…` di una notizia scritta solo in italiano mostrava l'italiano con
 * un canonical su se stessa: Search Console (26/10/2026) la segnala come
 * doppione con «canonica diversa da quella specificata». Come le schede
 * prodotto, finche' non e' tradotta la versione inglese non si propone a Google.
 */
class NotizieSenzaTraduzioneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function notizia(array $titolo, array $testo = []): Post
    {
        return Post::factory()->create([
            'title' => $titolo,
            'content' => $testo,
        ]);
    }

    #[Test]
    public function una_notizia_e_tradotta_solo_con_titolo_e_testo_in_inglese(): void
    {
        $this->assertFalse($this->notizia(['it' => 'Derby'])->tradottoIn('en'));
        $this->assertFalse($this->notizia(['it' => 'Derby', 'en' => 'Derby'], ['it' => '<p>Testo</p>'])->tradottoIn('en'));
        $this->assertTrue($this->notizia(['it' => 'Derby', 'en' => 'Derby'], ['it' => '<p>Testo</p>', 'en' => '<p>Text</p>'])->tradottoIn('en'));
        $this->assertTrue($this->notizia(['it' => 'Derby'])->tradottoIn('it'));
    }

    #[Test]
    public function la_notizia_senza_traduzione_non_dichiara_l_inglese_e_in_inglese_e_noindex(): void
    {
        $notizia = $this->notizia(['it' => 'Derby'], ['it' => '<p>Testo</p>']);

        $italiana = $this->get(route('news.show', $notizia->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString('hreflang="en"', $italiana);
        $this->assertStringNotContainsString('noindex', $italiana);

        $inglese = $this->get(route('en.news.show', $notizia->slug))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $inglese);
    }

    #[Test]
    public function la_notizia_tradotta_resta_nelle_due_lingue(): void
    {
        $notizia = $this->notizia(['it' => 'Derby', 'en' => 'Derby'], ['it' => '<p>Testo</p>', 'en' => '<p>Text</p>']);

        $inglese = $this->get(route('en.news.show', $notizia->slug))->assertOk()->getContent();

        $this->assertStringContainsString('hreflang="it"', $inglese);
        $this->assertStringContainsString('hreflang="en"', $inglese);
        $this->assertStringNotContainsString('noindex', $inglese);
    }

    #[Test]
    public function la_sitemap_elenca_in_inglese_solo_le_notizie_tradotte(): void
    {
        $tradotta = $this->notizia(['it' => 'Derby', 'en' => 'Derby'], ['it' => '<p>Testo</p>', 'en' => '<p>Text</p>']);
        $soloItaliano = $this->notizia(['it' => 'Play off'], ['it' => '<p>Gara 2</p>']);

        $xml = $this->get('/sitemap-notizie.xml')->assertOk()->getContent();

        $this->assertStringContainsString(route('en.news.show', $tradotta->slug), $xml);
        $this->assertStringContainsString(route('news.show', $soloItaliano->slug), $xml);
        $this->assertStringNotContainsString(route('en.news.show', $soloItaliano->slug), $xml);
    }
}
