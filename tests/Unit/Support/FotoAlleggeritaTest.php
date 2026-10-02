<?php

namespace Tests\Unit\Support;

use App\Models\GalleryImage;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Support\FotoAlleggerita;
use PHPUnit\Framework\TestCase;

class FotoAlleggeritaTest extends TestCase
{
    private string $cartella;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartella = sys_get_temp_dir().'/foto-alleggerita-'.uniqid();
        mkdir($this->cartella);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cartella.'/*') ?: []);
        rmdir($this->cartella);
        parent::tearDown();
    }

    public function test_la_foto_da_fotocamera_scende_a_2560_px_e_pesa_meno(): void
    {
        $percorso = $this->foto('foto.jpg', 6000, 4000, fn ($i, $p) => imagejpeg($i, $p, 98));
        $prima = filesize($percorso);

        FotoAlleggerita::alleggerisci($percorso);

        clearstatcache();
        [$larghezza, $altezza, $tipo] = getimagesize($percorso);
        $this->assertSame([2560, 1707, IMAGETYPE_JPEG], [$larghezza, $altezza, $tipo]);
        $this->assertLessThan($prima, filesize($percorso));
    }

    public function test_la_foto_gia_piccola_non_si_ingrandisce_ne_peggiora(): void
    {
        $percorso = $this->foto('piccola.jpg', 800, 600, fn ($i, $p) => imagejpeg($i, $p, 60));
        $prima = file_get_contents($percorso);

        FotoAlleggerita::alleggerisci($percorso);

        // Ricodificata a 86 peserebbe di piu': si tiene l'originale.
        $this->assertSame($prima, file_get_contents($percorso));
    }

    public function test_il_png_resta_png_con_la_trasparenza(): void
    {
        $percorso = $this->cartella.'/logo.png';
        $immagine = imagecreatetruecolor(4000, 4000);
        imagealphablending($immagine, false);
        imagesavealpha($immagine, true);
        imagefilledrectangle($immagine, 0, 0, 3999, 3999, imagecolorallocatealpha($immagine, 0, 0, 0, 127));
        imagefilledrectangle($immagine, 2000, 2000, 3999, 3999, imagecolorallocate($immagine, 0, 48, 99));
        imagepng($immagine, $percorso, 0);

        FotoAlleggerita::alleggerisci($percorso);

        $this->assertSame([2560, 2560, IMAGETYPE_PNG], array_slice(getimagesize($percorso), 0, 3));
        $alfa = (imagecolorat(imagecreatefrompng($percorso), 10, 10) >> 24) & 0x7F;
        $this->assertSame(127, $alfa);
    }

    public function test_il_profilo_colore_del_jpeg_resta(): void
    {
        $percorso = $this->foto('p3.jpg', 6000, 4000, fn ($i, $p) => imagejpeg($i, $p, 95));
        $profilo = "ICC_PROFILE\0\x01\x01".str_repeat('P3', 300);
        $segmento = "\xFF\xE2".pack('n', strlen($profilo) + 2).$profilo;
        $jpeg = file_get_contents($percorso);
        file_put_contents($percorso, substr($jpeg, 0, 2).$segmento.substr($jpeg, 2));

        FotoAlleggerita::alleggerisci($percorso);

        $risultato = file_get_contents($percorso);
        $this->assertSame(2560, getimagesize($percorso)[0]);
        $this->assertStringContainsString($segmento, $risultato);
        $this->assertNotFalse(imagecreatefromstring($risultato), 'il JPEG con il profilo deve restare leggibile');
    }

    public function test_png_con_profilo_colore_non_si_tocca(): void
    {
        $percorso = $this->cartella.'/profilo.png';
        imagepng(imagecreatetruecolor(4000, 4000), $percorso, 0);
        // Chunk iCCP finto subito dopo IHDR (8 + 25 byte).
        $png = file_get_contents($percorso);
        $dati = "sRGB\0\0".gzcompress('profilo');
        $chunk = pack('N', strlen($dati)).'iCCP'.$dati.pack('N', crc32('iCCP'.$dati));
        file_put_contents($percorso, substr($png, 0, 33).$chunk.substr($png, 33));
        $prima = file_get_contents($percorso);

        FotoAlleggerita::alleggerisci($percorso);

        $this->assertSame($prima, file_get_contents($percorso));
    }

    public function test_la_foto_gia_piccola_e_leggera_non_si_ricodifica(): void
    {
        $percorso = $this->foto('leggera.jpg', 1600, 1200, fn ($i, $p) => imagejpeg($i, $p, 95));
        $this->assertLessThan(FotoAlleggerita::PESO_DA_RICOMPRIMERE, filesize($percorso));
        $prima = file_get_contents($percorso);

        FotoAlleggerita::alleggerisci($percorso);

        $this->assertSame($prima, file_get_contents($percorso));
    }

    public function test_cio_che_non_e_una_foto_non_si_tocca(): void
    {
        $percorso = $this->cartella.'/documento.pdf';
        file_put_contents($percorso, "%PDF-1.4\n%falso\n");

        FotoAlleggerita::alleggerisci($percorso);

        $this->assertSame("%PDF-1.4\n%falso\n", file_get_contents($percorso));
    }

    public function test_la_gallery_resta_originale_per_il_riconoscimento_dei_volti(): void
    {
        $this->assertNull(FotoAlleggerita::latoMassimoPer(new GalleryImage));
        $this->assertSame(3840, FotoAlleggerita::latoMassimoPer(new HeroSlide));
        $this->assertSame(FotoAlleggerita::LATO_MASSIMO, FotoAlleggerita::latoMassimoPer(new Product));
        $this->assertSame(FotoAlleggerita::LATO_MASSIMO, FotoAlleggerita::latoMassimoPer(null));
    }

    private function foto(string $nome, int $larghezza, int $altezza, callable $salva): string
    {
        $immagine = imagecreatetruecolor($larghezza, $altezza);
        for ($n = 0; $n < $larghezza * $altezza / 50; $n++) {
            imagesetpixel($immagine, mt_rand(0, $larghezza - 1), mt_rand(0, $altezza - 1), mt_rand(0, 0xFFFFFF));
        }
        $percorso = $this->cartella.'/'.$nome;
        $salva($immagine, $percorso);

        return $percorso;
    }
}
