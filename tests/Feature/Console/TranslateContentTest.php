<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslateContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_traduce_i_campi_rimasti_in_italiano(): void
    {
        $product = Product::factory()->create([
            'name' => ['it' => 'Zaino', 'en' => 'Zaino'],
            'description' => ['it' => '<p>T-shirt ufficiale Erreà della Final Four di Champions League 2026 a Istanbul</p>', 'en' => '<p>T-shirt ufficiale Erreà della Final Four di Champions League 2026 a Istanbul</p>'],
        ]);

        $this->artisan('content:translate-missing')->assertSuccessful();

        $product->refresh();

        $this->assertSame('Backpack', $product->getTranslation('name', 'en'));
        $this->assertSame(
            '<p>Official Erreà t-shirt for the 2026 Champions League Final Four in Istanbul</p>',
            $product->getTranslation('description', 'en'),
        );
        // L'italiano non viene toccato.
        $this->assertSame('Zaino', $product->getTranslation('name', 'it'));
    }

    public function test_non_sovrascrive_le_traduzioni_fatte_in_redazione(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => ['it' => 'Kit Gara 25-26', 'en' => 'Game Kit 25-26'],
        ]);

        $this->artisan('content:translate-missing')->assertSuccessful();

        $this->assertSame('Game Kit 25-26', $category->refresh()->getTranslation('name', 'en'));
    }

    public function test_il_dry_run_non_scrive_nulla(): void
    {
        $product = Product::factory()->create([
            'name' => ['it' => 'Tazza', 'en' => 'Tazza'],
            'description' => ['it' => '', 'en' => ''],
        ]);

        $this->artisan('content:translate-missing', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('Tazza', $product->refresh()->getTranslation('name', 'en'));
    }

    public function test_segnala_i_testi_senza_traduzione_e_fallisce(): void
    {
        Product::factory()->create([
            'name' => ['it' => 'Prodotto mai visto', 'en' => 'Prodotto mai visto'],
            'description' => ['it' => '', 'en' => ''],
        ]);

        $this->artisan('content:translate-missing')
            ->expectsOutputToContain('Traduzione mancante per 1 campi:')
            ->assertFailed();
    }

    public function test_traduce_ruoli_dello_staff_e_categorie_delle_news(): void
    {
        $staff = StaffMember::factory()->create([
            'role' => ['it' => 'Primo Allenatore', 'en' => 'Primo Allenatore'],
        ]);
        $category = Category::factory()->create([
            'name' => ['it' => 'Notizie', 'en' => ''],
        ]);

        $this->artisan('content:translate-missing')->assertSuccessful();

        $this->assertSame('Head Coach', $staff->refresh()->getTranslation('role', 'en'));
        $this->assertSame('News', $category->refresh()->getTranslation('name', 'en'));
    }

    public function test_traduce_le_pagine_per_slug(): void
    {
        // La pagina esiste già nel database di test: la riportiamo allo stato
        // pre-traduzione invece di crearne una seconda con lo stesso slug.
        $page = Page::updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => ['it' => 'Privacy Policy', 'en' => 'Privacy Policy'],
                'content' => ['it' => '<h2>Informativa sulla Privacy</h2>', 'en' => ''],
                'status' => 'publish',
            ],
        );

        $this->artisan('content:translate-missing')->assertSuccessful();

        $page->refresh();

        $this->assertStringContainsString('Privacy Notice', $page->getTranslation('content', 'en'));
        $this->assertSame('<h2>Informativa sulla Privacy</h2>', $page->getTranslation('content', 'it'));
    }

    public function test_una_pagina_senza_testo_italiano_non_viene_riempita(): void
    {
        $page = Page::updateOrCreate(
            ['slug' => 'safeguarding'],
            [
                'title' => ['it' => 'Safeguarding', 'en' => 'Safeguarding'],
                'content' => ['it' => '', 'en' => ''],
                'status' => 'publish',
            ],
        );

        $this->artisan('content:translate-missing')->assertSuccessful();

        $this->assertSame('', $page->refresh()->getTranslation('content', 'en'));
    }
}
