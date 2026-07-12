<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_page_returns_200(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_stagione_page_returns_200(): void
    {
        $response = $this->get('/stagione');
        $response->assertStatus(200);
    }

    public function test_cms_page_returns_200_for_published_page(): void
    {
        Page::factory()->create([
            'slug' => 'test-page',
            'title' => 'Test Page',
            'status' => PostStatus::Published,
            'content' => '<p>Test content</p>',
        ]);

        $response = $this->get('/test-page');
        $response->assertStatus(200);
    }

    public function test_societa_page_returns_200_for_published_page(): void
    {
        Page::factory()->create([
            'slug' => 'storia',
            'title' => 'Storia',
            'status' => PostStatus::Published,
            'template' => 'Public/Societa/Storia',
            'content' => '<p>Test content</p>',
        ]);

        $response = $this->get('/societa/storia');
        $response->assertStatus(200);
    }

    public function test_societa_root_redirects_to_storia(): void
    {
        $response = $this->get('/societa');
        $response->assertRedirect('/societa/storia');
    }

    public function test_en_societa_root_redirects_to_en_storia(): void
    {
        $response = $this->get('/en/societa');
        $response->assertRedirect('/en/societa/storia');
    }

    public function test_seo_catch_all_redirects_societa_pages_to_canonical_route(): void
    {
        Page::factory()->create([
            'slug' => 'storia',
            'title' => 'Storia',
            'status' => PostStatus::Published,
            'template' => 'Public/Societa/Storia',
            'content' => '<p>Test content</p>',
        ]);

        // Italian catch-all redirect
        $response = $this->get('/storia');
        $response->assertRedirect('/societa/storia');
        $response->assertStatus(301);

        // English catch-all redirect
        $responseEn = $this->get('/en/storia');
        $responseEn->assertRedirect('/en/societa/storia');
        $responseEn->assertStatus(301);
    }

    public function test_cms_page_returns_404_for_draft_page(): void
    {
        Page::factory()->create([
            'slug' => 'draft-page',
            'title' => 'Draft Page',
            'status' => PostStatus::Draft,
        ]);

        $response = $this->get('/draft-page');
        $response->assertStatus(404);
    }

    public function test_nonexistent_slug_returns_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');
        $response->assertStatus(404);
    }

    // --- Nuovi test per le sottovoci dei menu e redirect ---

    public function test_sponsor_subpages_return_200(): void
    {
        Page::factory()->create([
            'slug' => 'diventa-sponsor',
            'title' => 'Diventa Sponsor',
            'status' => PostStatus::Published,
            'template' => 'Public/Sponsor',
        ]);

        $response = $this->get('/sponsor/diventa-sponsor');
        $response->assertStatus(200);
    }

    public function test_ticketing_subpages_return_200(): void
    {
        Page::factory()->create([
            'slug' => 'abbonamenti',
            'title' => 'Campagna Abbonamenti',
            'status' => PostStatus::Published,
            'template' => 'Public/Ticketing',
        ]);

        $response = $this->get('/ticketing/abbonamenti');
        $response->assertStatus(200);
    }

    public function test_youth_subpages_return_200(): void
    {
        Page::factory()->create([
            'slug' => 'settore-giovanile',
            'title' => 'Settore Giovanile',
            'status' => PostStatus::Published,
            'template' => 'Public/Youth',
        ]);

        $response = $this->get('/youth/settore-giovanile');
        $response->assertStatus(200);
    }

    public function test_sociale_subpages_return_200(): void
    {
        Page::factory()->create([
            'slug' => 'volley-4-all',
            'title' => 'Volley 4 All',
            'status' => PostStatus::Published,
            'template' => 'Public/Sociale',
        ]);

        $response = $this->get('/sociale/volley-4-all');
        $response->assertStatus(200);
    }

    public function test_comunicazione_subpages_return_200(): void
    {
        Page::factory()->create([
            'slug' => 'accrediti-stampa',
            'title' => 'Accrediti Stampa',
            'status' => PostStatus::Published,
            'template' => 'Public/Comunicazione',
        ]);

        $response = $this->get('/comunicazione/accrediti-stampa');
        $response->assertStatus(200);

        Page::factory()->create([
            'slug' => 'magazine',
            'title' => 'Magazine',
            'status' => PostStatus::Published,
            'template' => 'Public/Comunicazione',
        ]);

        $response2 = $this->get('/comunicazione/magazine');
        $response2->assertStatus(200);
    }

    public function test_sponsor_legacy_redirects(): void
    {
        $response = $this->get('/sponsor/nostri-sponsor');
        $response->assertRedirect('/sponsor');
        $response->assertStatus(301);
    }

    public function test_youth_redirects(): void
    {
        $response1 = $this->get('/youth/b1-u19');
        $response1->assertRedirect('/stagione/b1');
        $response1->assertStatus(301);

        $response2 = $this->get('/youth/u17-u15');
        $response2->assertRedirect('/in-costruzione');
        $response2->assertStatus(301);
    }

    public function test_summer_camp_redirects(): void
    {
        $response1 = $this->get('/summer-camp/info');
        $response1->assertRedirect('/summer-camp');
        $response1->assertStatus(301);

        $response2 = $this->get('/summer-camp/iscrizione');
        $response2->assertRedirect('/summer-camp/iscrizione-experience');
        $response2->assertStatus(301);
    }

    public function test_sociale_redirects(): void
    {
        $response1 = $this->get('/sociale/progetti');
        $response1->assertRedirect('/sociale/progetti-sociali');
        $response1->assertStatus(301);

        $response2 = $this->get('/sociale/aste');
        $response2->assertRedirect('/shop/aste');
        $response2->assertStatus(301);
    }

    public function test_comunicazione_redirects(): void
    {
        $response1 = $this->get('/comunicazione/accrediti');
        $response1->assertRedirect('/comunicazione/accrediti-stampa');
        $response1->assertStatus(301);

        $response2 = $this->get('/comunicazione/cartelle');
        $response2->assertRedirect('/comunicazione/cartelle-stampa');
        $response2->assertStatus(301);
    }

    public function test_seo_canonical_redirects_across_multiple_sections(): void
    {
        // 1. Sponsor
        Page::factory()->create([
            'slug' => 'diventa-sponsor',
            'title' => 'Diventa Sponsor',
            'status' => PostStatus::Published,
            'template' => 'Public/Sponsor',
        ]);
        $responseSponsor = $this->get('/diventa-sponsor');
        $responseSponsor->assertRedirect('/sponsor/diventa-sponsor');
        $responseSponsor->assertStatus(301);

        // 2. Ticketing
        Page::factory()->create([
            'slug' => 'abbonamenti',
            'title' => 'Campagna Abbonamenti',
            'status' => PostStatus::Published,
            'template' => 'Public/Ticketing',
        ]);
        $responseTicketing = $this->get('/abbonamenti');
        $responseTicketing->assertRedirect('/ticketing/abbonamenti');
        $responseTicketing->assertStatus(301);

        // 3. Sociale
        Page::factory()->create([
            'slug' => 'volley-4-all',
            'title' => 'Volley 4 All',
            'status' => PostStatus::Published,
            'template' => 'Public/Sociale',
        ]);
        $responseSociale = $this->get('/volley-4-all');
        $responseSociale->assertRedirect('/sociale/volley-4-all');
        $responseSociale->assertStatus(301);
    }
}
