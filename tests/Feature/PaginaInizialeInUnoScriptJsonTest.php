<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit SEO del 14/10/2026: nell'attributo `data-page` ogni virgoletta del JSON
 * diventava `&quot;`. La pagina iniziale ora sta in uno script JSON, con `<` e
 * `>` neutralizzati perché nessun testo possa chiudere lo script o aprirne uno.
 */
class PaginaInizialeInUnoScriptJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    #[Test]
    public function la_pagina_iniziale_sta_in_uno_script_json_e_non_nell_attributo(): void
    {
        $html = $this->get(route('news.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"Public/News"', $html);
        $this->assertStringContainsString('<div id="app"></div>', $html);
        $this->assertStringNotContainsString('data-page="{', $html);
    }

    #[Test]
    public function un_testo_con_tag_html_non_esce_dallo_script(): void
    {
        $titolo = 'Prova </script><!--<script>alert(1)</script>--> «àè»';
        Post::factory()->create(['status' => PostStatus::Published, 'title' => $titolo, 'excerpt' => '']);

        $html = $this->get(route('news.index'))->assertOk()->getContent();

        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $script);

        $this->assertNotEmpty($script, 'Lo script della pagina iniziale non si chiude dove deve.');
        $this->assertStringNotContainsString('<', $script[1]);
        $this->assertStringContainsString('«àè»', $script[1], 'Le lettere accentate viaggiano in chiaro.');
        $this->assertSame($titolo, json_decode($script[1], true)['props']['posts']['data'][0]['title']);
    }
}
