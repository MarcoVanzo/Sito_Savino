<?php

namespace Tests\Feature\Console;

use App\Models\Post;
use App\Support\Accessibilita\TitoliDelleNotizie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'archivio importato da WordPress: titoli che partono da h3/h4 (309 notizie
 * su 958 a settembre 2026), un h1 nel testo, `aria-level` sui paragrafi. Il
 * comando corregge solo quello che non richiede di scrivere testo, rispetta il
 * formato della riga (JSON per lingua o testo semplice) ed è idempotente.
 */
class CorreggiLAccessibilitaDelleNotizieTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function i_titoli_scalano_insieme_finche_il_piu_alto_e_h2(): void
    {
        $esito = TitoliDelleNotizie::correggi('<h4>A</h4><p>x</p><h5>B</h5><h4>C</h4>');

        $this->assertSame('<h2>A</h2><p>x</p><h3>B</h3><h2>C</h2>', $esito['html']);
        $this->assertSame(-2, $esito['scarto']);
    }

    #[Test]
    public function un_h1_nel_testo_scende_a_h2(): void
    {
        $this->assertSame('<h2 class="x">T</h2><h3>S</h3>', TitoliDelleNotizie::correggi('<h1 class="x">T</h1><h2>S</h2>')['html']);
    }

    #[Test]
    public function aria_level_si_toglie_dai_paragrafi_ma_non_dai_titoli_veri(): void
    {
        $esito = TitoliDelleNotizie::correggi('<p aria-level="4">a</p><div role="heading" aria-level="3">b</div>');

        $this->assertSame('<p>a</p><div role="heading" aria-level="3">b</div>', $esito['html']);
        $this->assertSame(1, $esito['aria_level']);
    }

    #[Test]
    public function il_testo_gia_a_posto_non_cambia_e_le_immagini_senza_alt_si_contano_soltanto(): void
    {
        $html = '<h2>A</h2><img src="a.jpg" alt=""><h3>B</h3>';
        $esito = TitoliDelleNotizie::correggi($html);

        $this->assertSame($html, $esito['html']);
        $this->assertSame(1, $esito['immagini_senza_testo']);
    }

    #[Test]
    public function si_contano_solo_le_immagini_ancora_senza_testo(): void
    {
        $esito = TitoliDelleNotizie::correggi('<img src="a.jpg" alt="Prezzi degli abbonamenti"><img src="b.jpg"><img src="c.jpg" alt=" ">');

        $this->assertSame(2, $esito['immagini_senza_testo']);
    }

    #[Test]
    public function il_comando_elenca_la_notizia_con_un_immagine_senza_alt(): void
    {
        $post = Post::factory()->create(['slug' => 'con-immagine']);
        DB::table('posts')->where('id', $post->id)->update(['content' => json_encode(['it' => '<h2>A</h2><img src="a.jpg" alt="">'])]);

        $this->artisan('news:correggi-accessibilita', ['--prova' => true])
            ->expectsOutputToContain('1 notizie hanno ancora immagini senza testo alternativo')
            ->assertSuccessful();
    }

    #[Test]
    public function con_prova_non_scrive_senza_prova_corregge_e_una_seconda_volta_non_trova_niente(): void
    {
        $tradotta = Post::factory()->create(['slug' => 'tradotta']);
        DB::table('posts')->where('id', $tradotta->id)->update(['content' => json_encode(['it' => '<h4>Titolo</h4><p aria-level="4">Testo</p>', 'en' => '<h3>Title</h3>'])]);
        $semplice = Post::factory()->create(['slug' => 'semplice']);
        DB::table('posts')->where('id', $semplice->id)->update(['content' => '<h3>Riga storica</h3>']);

        $this->artisan('news:correggi-accessibilita', ['--prova' => true])
            ->expectsOutputToContain('Da correggere: 2 notizie')
            ->assertSuccessful();
        $this->assertStringContainsString('<h4>', DB::table('posts')->where('id', $tradotta->id)->value('content'));

        $this->artisan('news:correggi-accessibilita')->expectsOutputToContain('Corrette: 2 notizie')->assertSuccessful();

        $this->assertSame(['it' => '<h2>Titolo</h2><p>Testo</p>', 'en' => '<h2>Title</h2>'], json_decode(DB::table('posts')->where('id', $tradotta->id)->value('content'), true));
        $this->assertSame('<h2>Riga storica</h2>', DB::table('posts')->where('id', $semplice->id)->value('content'));

        $this->artisan('news:correggi-accessibilita')->expectsOutputToContain('Corrette: 0 notizie')->assertSuccessful();
    }
}
