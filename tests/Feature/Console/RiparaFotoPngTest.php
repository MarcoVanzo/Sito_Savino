<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiparaFotoPngTest extends TestCase
{
    use RefreshDatabase;

    public function test_toglie_il_profilo_e_rigenera_le_conversioni(): void
    {
        Storage::fake('public');
        $prodotto = Product::factory()->create();
        $media = $prodotto->addMediaFromString($this->pngConProfilo())
            ->usingFileName('foto.png')
            ->toMediaCollection('images');
        // Com'erano le otto foto del 02/10/2026: nessuna conversione.
        $media->forceFill(['generated_conversions' => []])->save();

        $this->artisan('foto:ripara-png --dry-run')->assertSuccessful();
        $this->assertStringContainsString('iCCP', Storage::disk('public')->get($media->getPathRelativeToRoot()));

        $this->artisan('foto:ripara-png')->assertSuccessful();

        $media->refresh();
        $this->assertStringNotContainsString('iCCP', Storage::disk('public')->get($media->getPathRelativeToRoot()));
        $this->assertTrue($media->hasGeneratedConversion('zoom'));
        $this->assertTrue($media->hasGeneratedConversion('thumb'));
    }

    private function pngConProfilo(): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(400, 300));
        $png = ob_get_clean();
        $dati = "sRGB\0\0".gzcompress('profilo difettoso');
        $chunk = pack('N', strlen($dati)).'iCCP'.$dati.pack('N', crc32('iCCP'.$dati));

        return substr($png, 0, 33).$chunk.substr($png, 33);
    }
}
