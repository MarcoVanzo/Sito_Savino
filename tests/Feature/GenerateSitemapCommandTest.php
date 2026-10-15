<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateSitemapCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function il_comando_riscalda_la_cache_della_sitemap(): void
    {
        Cache::flush();

        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('sitemap-pagine.xml aggiornata in cache')
            ->expectsOutputToContain('sitemap-gallery.xml aggiornata in cache')
            ->assertSuccessful();

        $xml = Cache::get('sitemap.xml:pagine');

        $this->assertIsString($xml);
        $this->assertStringContainsString('<urlset', $xml);

        // La rotta serve la stessa cache appena riscaldata.
        Cache::put('sitemap.xml:pagine', '<urlset><!-- dalla cache --></urlset>');
        $this->get('/sitemap-pagine.xml')->assertStatus(200)->assertSee('dalla cache', false);
    }
}
