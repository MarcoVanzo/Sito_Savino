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
}
