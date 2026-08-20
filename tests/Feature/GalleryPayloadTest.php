<?php

namespace Tests\Feature;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
    public function ogni_foto_porta_l_album_a_cui_appartiene(): void
    {
        $evento = GalleryEvent::factory()->create([
            'title' => ['it' => 'Savino Del Bene — Numia Milano', 'en' => 'Savino Del Bene — Numia Milano'],
            'event_date' => '2026-10-05',
            'is_active' => true,
        ]);

        GalleryImage::factory()->create(['gallery_event_id' => $evento->id, 'is_active' => true]);

        $foto = $this->getJson('/gallery/data')->assertStatus(200)->json('media.0');

        // Senza questi due campi la pagina non puo' costruire le cartelle:
        // il titolo da solo non basta, due eventi possono chiamarsi uguale.
        $this->assertSame($evento->id, $foto['event_id']);
        $this->assertSame('2026-10-05', $foto['event_date']);
        $this->assertSame('Savino Del Bene — Numia Milano', $foto['event_name']);
    }

    #[Test]
    public function una_foto_senza_album_non_inventa_un_evento(): void
    {
        GalleryImage::factory()->create(['gallery_event_id' => null, 'is_active' => true]);

        $foto = $this->getJson('/gallery/data')->assertStatus(200)->json('media.0');

        $this->assertNull($foto['event_id']);
        $this->assertNull($foto['event_date']);
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

    #[Test]
    public function l_endpoint_per_atleta_filtra_sulle_sue_foto(): void
    {
        $player = Player::factory()->create();
        GalleryImage::factory()->count(2)->create(['is_active' => true])
            ->each(fn (GalleryImage $img) => $img->players()->attach($player));
        GalleryImage::factory()->count(3)->create(['is_active' => true]);

        $slug = $player->id.'-'.Str::slug($player->full_name);

        $this->getJson("/gallery/atleta/{$slug}/data")
            ->assertStatus(200)
            ->assertJsonCount(2, 'media');
    }

    #[Test]
    public function l_atleta_inesistente_da_404(): void
    {
        $this->getJson('/gallery/atleta/99999-nessuno/data')->assertStatus(404);
    }
}
