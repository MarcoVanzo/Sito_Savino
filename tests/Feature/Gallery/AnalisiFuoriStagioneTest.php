<?php

namespace Tests\Feature\Gallery;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Services\FacialRecognitionService;
use App\Support\StagioniDelleAtlete;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'analisi non tagga un'atleta su una foto di una stagione in cui giocava
 * altrove: è uno scambio di volto (StagioniDelleAtlete).
 */
class AnalisiFuoriStagioneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        StagioniDelleAtlete::usa(null);
        parent::tearDown();
    }

    #[Test]
    public function scarta_le_atlete_che_non_erano_in_squadra(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $nuova = Player::factory()->create(['first_name' => 'Kiera', 'last_name' => 'Van Ryk']);
        $veterana = Player::factory()->create(['first_name' => 'Linda', 'last_name' => 'Nwakalor']);
        StagioniDelleAtlete::usa(['Kiera Van Ryk' => [], 'Linda Nwakalor' => [2025]]);

        $album = GalleryEvent::factory()->create(['event_date' => '2025-11-10']);
        $foto = GalleryImage::factory()->create(['gallery_event_id' => $album->id]);
        $foto->addMedia(UploadedFile::fake()->image('partita.jpg'))->toMediaCollection('gallery');

        $this->mock(FacialRecognitionService::class)->shouldReceive('recognizeFaces')->andReturn([
            'detected_persons' => [
                ['person_type' => Player::class, 'person_id' => $nuova->id, 'confidence' => 0.99],
                ['person_type' => Player::class, 'person_id' => $veterana->id, 'confidence' => 0.99],
            ],
            'has_unrecognized_faces' => false,
        ]);

        app()->call([new AnalyzeGalleryImageJob($foto), 'handle']);

        $taggate = DB::table('gallery_image_person')->where('gallery_image_id', $foto->id)->pluck('person_id')->all();
        $this->assertSame([$veterana->id], $taggate);
        $this->assertStringNotContainsString('Van Ryk', (string) $foto->fresh()->title);
    }
}
