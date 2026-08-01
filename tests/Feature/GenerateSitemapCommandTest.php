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
            ->expectsOutputToContain('Sitemap aggiornata in cache')
            ->assertSuccessful();

        $xml = Cache::get('sitemap.xml');

        $this->assertIsString($xml);
        $this->assertStringContainsString('<urlset', $xml);

        // La rotta serve la stessa cache appena riscaldata.
        $this->get('/sitemap.xml')->assertStatus(200)->assertSee('<urlset', false);
    }
}
