<?php

namespace Tests\Feature\Console;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Support\StagioniDelleAtlete;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le nuove arrivate finivano riconosciute su foto di anni prima: i tag
 * automatici fuori stagione si tolgono, quelli della redazione restano.
 */
class TogliITagFuoriStagioneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        StagioniDelleAtlete::usa(null);
        parent::tearDown();
    }

    private function foto(string $data, ?string $titolo = null): GalleryImage
    {
        $album = GalleryEvent::factory()->create(['event_date' => $data, 'title' => 'Partita']);

        return GalleryImage::factory()->create(['gallery_event_id' => $album->id, 'title' => $titolo]);
    }

    private function tagga(GalleryImage $foto, Player $atleta, ?float $somiglianza): void
    {
        DB::table('gallery_image_person')->insert([
            'gallery_image_id' => $foto->id,
            'person_type' => Player::class,
            'person_id' => $atleta->id,
            'confidence_score' => $somiglianza,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tagDi(Player $atleta): int
    {
        return DB::table('gallery_image_person')->where('person_id', $atleta->id)->count();
    }

    #[Test]
    public function la_stagione_va_da_luglio_a_giugno(): void
    {
        $this->assertSame(2025, StagioniDelleAtlete::stagioneDi(now()->setDate(2026, 6, 30)));
        $this->assertSame(2026, StagioniDelleAtlete::stagioneDi(now()->setDate(2026, 7, 1)));
    }

    #[Test]
    public function toglie_solo_i_tag_automatici_fuori_stagione(): void
    {
        $nuova = Player::factory()->create(['first_name' => 'Kiera', 'last_name' => 'Van Ryk']);
        $veterana = Player::factory()->create(['first_name' => 'Linda', 'last_name' => 'Nwakalor']);
        $sconosciuta = Player::factory()->create(['first_name' => 'Mai', 'last_name' => 'Elencata']);
        StagioniDelleAtlete::usa(['Kiera Van Ryk' => [], 'Linda Nwakalor' => [2024, 2025]]);

        $vecchia = $this->foto('2025-11-10', 'Kiera Van Ryk, Linda Nwakalor - Partita - 10/11/2025');
        $this->tagga($vecchia, $nuova, 0.99);
        $this->tagga($vecchia, $veterana, 0.99);
        $this->tagga($vecchia, $sconosciuta, 0.99);
        $this->tagga($this->foto('2025-12-01'), $nuova, null); // messo dalla redazione

        $this->artisan('volti:togli-fuori-stagione', ['--dry-run' => true])
            ->expectsOutputToContain('Prova: 1 tag da togliere su 1 foto')
            ->assertSuccessful();
        $this->assertSame(2, $this->tagDi($nuova));

        $this->artisan('volti:togli-fuori-stagione')
            ->expectsOutputToContain('Tolti 1 tag su 1 foto')
            ->assertSuccessful();

        $this->assertSame(1, $this->tagDi($nuova), 'resta solo il tag della redazione');
        $this->assertSame(1, $this->tagDi($veterana));
        $this->assertSame(1, $this->tagDi($sconosciuta), 'chi non è nel file non viene giudicata');
        $this->assertStringNotContainsString('Van Ryk', (string) $vecchia->fresh()->title);
    }

    #[Test]
    public function la_stagione_in_corso_viene_dalle_rose(): void
    {
        $atleta = Player::factory()->create(['first_name' => 'Kiera', 'last_name' => 'Van Ryk']);
        StagioniDelleAtlete::usa(['Kiera Van Ryk' => []]);
        Roster::factory()->create([
            'player_id' => $atleta->id,
            'season_id' => Season::factory()->create(['name' => '2026/2027', 'lvf_season_year' => 2026])->id,
        ]);

        $this->tagga($this->foto('2026-10-01'), $atleta, 0.99);

        $this->artisan('volti:togli-fuori-stagione')->expectsOutputToContain('Tolti 0 tag')->assertSuccessful();
        $this->assertSame(1, $this->tagDi($atleta));
    }
}
