<?php

namespace Tests\Feature;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Services\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La sitemap degli album legge un album per volta e solo le prime venti
 * foto, senza l'archivio completo: lo scheduler da 0,5 GB andava fuori
 * memoria alle 04:00 (24-28/11/2026).
 */
class SitemapDegliAlbumDellaGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    #[Test]
    public function ogni_album_porta_al_piu_venti_foto_attive_col_suo_slug(): void
    {
        $evento = GalleryEvent::factory()->create([
            'title' => ['it' => 'Savino contro Conegliano', 'en' => 'Savino contro Conegliano'],
            'event_date' => '2026-10-05',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 22; $i++) {
            $foto = GalleryImage::factory()->create(['gallery_event_id' => $evento->id, 'is_active' => true, 'sort_order' => $i]);
            $foto->addMedia(UploadedFile::fake()->image("foto-{$i}.jpg", 40, 30))->toMediaCollection('gallery', 'public');
        }

        $spenta = GalleryImage::factory()->create(['gallery_event_id' => $evento->id, 'is_active' => false, 'sort_order' => -1]);
        $spenta->addMedia(UploadedFile::fake()->image('spenta.jpg', 40, 30))->toMediaCollection('gallery', 'public');

        $senzaFoto = GalleryEvent::factory()->create(['title' => ['it' => 'Album vuoto'], 'is_active' => true]);
        GalleryImage::factory()->create(['gallery_event_id' => $senzaFoto->id, 'is_active' => true]);

        $xml = app(SitemapBuilder::class)->render('gallery');

        $this->assertStringContainsString(url("/gallery/album/{$evento->id}-savino-contro-conegliano"), $xml);
        $this->assertStringNotContainsString("/gallery/album/{$senzaFoto->id}", $xml);
        $this->assertStringNotContainsString('spenta.jpg', $xml);
        $this->assertStringContainsString('foto-0', $xml);
        $this->assertStringContainsString('foto-19', $xml);
        $this->assertStringNotContainsString('foto-20', $xml);
        $this->assertStringNotContainsString('foto-21', $xml);
    }
}
