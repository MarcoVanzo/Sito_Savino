<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    // --- Rotte Statiche ---

    public function test_home_returns_200(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_stagione_returns_200(): void
    {
        $this->get('/stagione')->assertStatus(200);
    }

    public function test_stagione_b1_returns_200(): void
    {
        $this->get('/stagione/b1')->assertStatus(200);
    }

    public function test_risultati_returns_200(): void
    {
        $this->get('/risultati')->assertStatus(200);
    }

    public function test_gallery_returns_200(): void
    {
        $this->get('/gallery')->assertStatus(200);
    }

    public function test_staff_returns_200(): void
    {
        $this->get('/staff')->assertStatus(200);
    }

    public function test_sponsor_returns_200(): void
    {
        $this->get('/sponsor')->assertStatus(200);
    }

    public function test_shop_returns_200(): void
    {
        $this->get('/shop')->assertStatus(200);
    }

    public function test_news_returns_200(): void
    {
        $this->get('/news')->assertStatus(200);
    }

    // --- Rendering Inertia ---

    public function test_home_renders_correct_component(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page->component('Public/Home')
        );
    }

    public function test_sponsor_page_includes_sponsor_data(): void
    {
        Sponsor::factory()->count(2)->create();

        $this->get('/sponsor')->assertInertia(fn ($page) => $page->component('Public/Sponsor')
            ->has('sponsors', 2)
        );
    }

    public function test_shop_page_shows_only_active_products(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->get('/shop')->assertInertia(fn ($page) => $page->component('Public/Shop')
            ->has('products', 1)
        );
    }

    // --- Security Headers su Rotte Pubbliche ---

    public function test_public_routes_have_security_headers(): void
    {
        $response = $this->get('/news');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }
}
