<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La migrazione che scrive gli alt delle immagini nelle notizie importate:
 * riempie solo `alt=""` sull'immagine giusta, non tocca un testo già scritto
 * dalla redazione, conserva il formato della riga e butta la cache della
 * notizia.
 */
class TestiAlternativiDelleNotizieTest extends TestCase
{
    use RefreshDatabase;

    private const SRC = 'https://sito-savino-assets-2026.fra1.digitaloceanspaces.com/news/2025/08/Tabella-prezzi-25-26-1024x1024.jpg';

    private function migrazione(): object
    {
        return require database_path('migrations/2026_09_26_170000_testi_alternativi_delle_immagini_delle_notizie.php');
    }

    private function notizia(int $id, string $html): void
    {
        Post::factory()->create(['id' => $id, 'slug' => 'notizia-'.$id]);
        DB::table('posts')->where('id', $id)->update(['content' => json_encode(['it' => $html])]);
    }

    private function testo(int $id): string
    {
        return json_decode(DB::table('posts')->where('id', $id)->value('content'), true)['it'];
    }

    #[Test]
    public function riempie_l_alt_vuoto_dell_immagine_giusta_e_butta_la_cache(): void
    {
        $this->notizia(9, '<p>Prezzi:</p><img loading="lazy" class="aligncenter" src="'.self::SRC.'" alt="" width="400" /><img src="https://esempio.test/altra.jpg" alt="" />');
        Cache::put('public:news:it:notizia-9', 'vecchia');

        $this->migrazione()->up();

        $html = $this->testo(9);
        $this->assertStringContainsString('src="'.self::SRC.'" alt="Believe, campagna abbonamenti 2025/2026: prezzi per settore', $html);
        $this->assertStringContainsString('Tribuna Ovest 420, 370, 260 euro', $html);
        // L'immagine che non e' nella mappa resta com'era.
        $this->assertStringContainsString('<img src="https://esempio.test/altra.jpg" alt="" />', $html);
        $this->assertNull(Cache::get('public:news:it:notizia-9'));
    }

    #[Test]
    public function un_alt_scritto_dalla_redazione_non_si_tocca_e_rigirarla_non_cambia_nulla(): void
    {
        $this->notizia(9, '<img src="'.self::SRC.'" alt="Scritto in redazione" />');
        $this->notizia(7, '<p>Il girone senza immagine.</p>');

        $this->migrazione()->up();
        $this->migrazione()->up();

        $this->assertSame('<img src="'.self::SRC.'" alt="Scritto in redazione" />', $this->testo(9));
        $this->assertSame('<p>Il girone senza immagine.</p>', $this->testo(7));
    }

    #[Test]
    public function gli_apostrofi_dell_alt_non_rompono_l_attributo(): void
    {
        $src = 'https://sito-savino-assets-2026.fra1.digitaloceanspaces.com/news/2023/05/46bb290e-aa4e-4c98-b0e5-c7c8c1223b9b-1024x683.jpg';
        $this->notizia(491, '<img src="'.$src.'" alt="" />');

        $this->migrazione()->up();

        $this->assertStringContainsString('alt="Una giocatrice della Savino Del Bene mostra il Gonfalone d&apos;Argento', $this->testo(491));
    }
}
