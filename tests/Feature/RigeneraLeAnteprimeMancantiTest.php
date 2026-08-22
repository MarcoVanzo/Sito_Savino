<?php

namespace Tests\Feature;

use App\Jobs\RigeneraLeAnteprimeMancanti;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nei mesi senza GD in produzione ogni conversione moriva e i job hanno
 * esaurito i tentativi: tremila immagini sono rimaste senza anteprima e la
 * gallery serve gli originali a piena risoluzione. Installare l'estensione
 * sistema le immagini nuove, non quelle vecchie — le rimette a posto questo
 * job, che lavora a blocchi e riparte da dove si era fermato.
 */
class RigeneraLeAnteprimeMancantiTest extends TestCase
{
    use RefreshDatabase;

    private function creaMedia(int $id, string $conversioni): void
    {
        DB::table('media')->insert([
            'id' => $id,
            'model_type' => 'App\Models\GalleryImage',
            'model_id' => $id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'gallery',
            'name' => 'foto-'.$id,
            'file_name' => 'foto-'.$id.'.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => $conversioni,
            'responsive_images' => '[]',
            'order_column' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function rigenera_solo_i_file_senza_anteprime(): void
    {
        Queue::fake();
        $this->creaMedia(9001, '[]');
        $this->creaMedia(9002, json_encode(['thumb' => true]));
        $this->creaMedia(9003, '{}');

        $chiamate = [];
        Artisan::shouldReceive('call')->andReturnUsing(function ($comando, $parametri) use (&$chiamate) {
            $chiamate[] = [$comando, $parametri];

            return 0;
        });

        (new RigeneraLeAnteprimeMancanti)->handle();

        $this->assertCount(1, $chiamate);
        [$comando, $parametri] = $chiamate[0];

        $this->assertSame('media-library:regenerate', $comando);
        $this->assertTrue($parametri['--only-missing']);
        $this->assertContains('9003', $parametri['--ids']);
        $this->assertContains('9001', $parametri['--ids']);
        $this->assertNotContains('9002', $parametri['--ids'], 'un file con le anteprime non va rifatto');
    }

    #[Test]
    public function rimette_in_coda_se_stesso_col_segnaposto(): void
    {
        Queue::fake();
        $this->creaMedia(9001, '[]');
        $this->creaMedia(9002, '[]');

        Artisan::shouldReceive('call')->andReturn(0);

        (new RigeneraLeAnteprimeMancanti)->handle();

        // Il blocco successivo riparte dal più piccolo appena fatto: un file
        // che non si riesce a convertire resta indietro invece di essere
        // ritentato all'infinito.
        Queue::assertPushed(RigeneraLeAnteprimeMancanti::class);
    }

    #[Test]
    public function si_ferma_quando_non_resta_niente(): void
    {
        Queue::fake();
        $this->creaMedia(9001, json_encode(['thumb' => true]));

        Artisan::shouldReceive('call')->never();

        (new RigeneraLeAnteprimeMancanti)->handle();

        Queue::assertNotPushed(RigeneraLeAnteprimeMancanti::class);
    }
}
