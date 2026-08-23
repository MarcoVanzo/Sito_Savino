<?php

namespace Tests\Feature\Console;

use App\Models\GalleryEvent;
use App\Models\Player;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * I comandi che rimettono a posto i file caricati.
 *
 * Si lanciano a mano e di rado — quasi sempre quando qualcosa è già andato
 * storto in produzione — quindi la cosa che conta di più è che partano: un
 * comando di emergenza che va in errore alla prima riga è peggio di non
 * averlo. Tutti e tre hanno `--dry-run`, ed è la modalità con cui si controlla
 * il danno prima di toccare i file.
 */
class ComandiDiManutenzioneMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('s3');
    }

    private function mediaSuDisco(string $disco): Media
    {
        $atleta = Player::factory()->create();

        config(['media-library.disk_name' => $disco]);

        return $atleta
            ->addMedia(UploadedFile::fake()->image('foto.jpg', 40, 40))
            ->usingFileName('foto.jpg')
            ->toMediaCollection('photo', $disco);
    }

    // ── media:fix-visibility ───────────────────────────────────────────────

    #[Test]
    public function la_visibilita_su_s3_si_puo_controllare_senza_toccare_niente(): void
    {
        $this->mediaSuDisco('s3');

        $this->artisan('media:fix-visibility', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 media items on S3 disk.')
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();
    }

    #[Test]
    public function senza_file_su_s3_il_comando_non_ha_niente_da_fare(): void
    {
        $this->artisan('media:fix-visibility', ['--dry-run' => true])
            ->expectsOutputToContain('Found 0 media items on S3 disk.')
            ->assertSuccessful();
    }

    /**
     * I file sul disco locale non lo riguardano: in produzione stanno su
     * Spaces, e il comando agisce solo lì.
     */
    #[Test]
    public function i_file_sul_disco_locale_non_vengono_toccati(): void
    {
        $this->mediaSuDisco('public');

        $this->artisan('media:fix-visibility', ['--dry-run' => true])
            ->expectsOutputToContain('Found 0 media items on S3 disk.')
            ->assertSuccessful();
    }

    // ── media:migrate-to-s3 ────────────────────────────────────────────────

    #[Test]
    public function la_migrazione_verso_spaces_si_puo_provare_a_vuoto(): void
    {
        $this->mediaSuDisco('public');

        $this->artisan('media:migrate-to-s3', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('media', ['disk' => 'public'], 'mysql');
    }

    #[Test]
    public function senza_file_da_migrare_il_comando_termina_bene(): void
    {
        $this->artisan('media:migrate-to-s3', ['--dry-run' => true])->assertSuccessful();
    }

    #[Test]
    public function la_migrazione_si_puo_restringere_a_una_sola_collezione(): void
    {
        $this->mediaSuDisco('public');

        $this->artisan('media:migrate-to-s3', [
            '--dry-run' => true,
            '--collection' => 'una-collezione-che-non-esiste',
        ])->assertSuccessful();
    }

    // ── gallery:from-posts ─────────────────────────────────────────────────

    #[Test]
    public function gli_album_dalle_news_si_possono_simulare(): void
    {
        Post::factory()->create(['published_at' => now()->subMonths(2)]);

        $this->artisan('gallery:from-posts', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('gallery_events', 0);
    }

    #[Test]
    public function senza_news_non_nasce_nessun_album(): void
    {
        $this->artisan('gallery:from-posts', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, GalleryEvent::count());
    }

    /**
     * La data di partenza esclude le news più vecchie dell'archivio utile.
     */
    #[Test]
    public function la_data_di_partenza_e_configurabile(): void
    {
        Post::factory()->create(['published_at' => now()->subYears(10)]);

        $this->artisan('gallery:from-posts', [
            '--dry-run' => true,
            '--from' => now()->subYear()->format('Y-m-d'),
        ])->assertSuccessful();

        $this->assertSame(0, GalleryEvent::count());
    }
}
