<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La gallery serializzava tutto l'archivio nella pagina: ~900 foto, mezzo
 * megabyte di HTML. Ora il primo render ne porta un blocco e il resto arriva
 * da `/gallery/data`.
 */
class GalleryPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    #[Test]
    public function la_pagina_porta_solo_il_primo_blocco_di_foto(): void
    {
        GalleryImage::factory()->count(130)->create(['is_active' => true]);

        $this->get('/gallery')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Public/Gallery')
                ->has('media', 120)
                ->where('mediaTotal', 130)
        );
    }

    #[Test]
    public function l_endpoint_restituisce_l_archivio_completo(): void
    {
        GalleryImage::factory()->count(130)->create(['is_active' => true]);

        $response = $this->getJson('/gallery/data');

        $response->assertStatus(200)->assertJsonCount(130, 'media');
    }

    #[Test]
    public function le_foto_disattivate_restano_fuori(): void
    {
        GalleryImage::factory()->count(3)->create(['is_active' => true]);
        GalleryImage::factory()->count(2)->create(['is_active' => false]);

        $this->getJson('/gallery/data')
            ->assertStatus(200)
            ->assertJsonCount(3, 'media');
    }
}
