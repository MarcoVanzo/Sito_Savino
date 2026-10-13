<?php

namespace Tests\Unit\Support;

use App\Enums\TipoCreativita;
use App\Enums\UsoCreativita;
use App\Support\Diretta\FormatiDelleCreativita;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FormatiDelleCreativitaTest extends TestCase
{
    /** @var list<string> */
    private array $temporanei = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->temporanei);
        parent::tearDown();
    }

    private function fixture(string $nome): string
    {
        return dirname(__DIR__, 2).'/Fixtures/Diretta/'.$nome;
    }

    private function png(int $larghezza, int $altezza, int $rumore = 0): string
    {
        $immagine = imagecreatetruecolor($larghezza, $altezza);

        // Pixel a caso: un PNG tinta unita si comprime a pochi KB.
        for ($i = 0; $i < $rumore; $i++) {
            imagesetpixel($immagine, random_int(0, $larghezza - 1), random_int(0, $altezza - 1), random_int(0, 0xFFFFFF));
        }

        $percorso = tempnam(sys_get_temp_dir(), 'png');
        imagepng($immagine, $percorso, $rumore > 0 ? 0 : 9);
        $this->temporanei[] = $percorso;

        return $percorso;
    }

    #[Test]
    public function l_immagine_1920x1080_leggera_va_bene(): void
    {
        $this->assertSame([], FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Immagine, $this->png(1920, 1080), 'image/png'));
    }

    #[Test]
    public function l_immagine_fuori_misura_dice_le_misure_giuste(): void
    {
        $errori = FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Immagine, $this->png(1280, 720), 'image/png');

        $this->assertSame(['L\'immagine è di 1280×720 px: deve essere di 1920×1080 px.'], $errori);
    }

    #[Test]
    public function l_immagine_troppo_pesante_si_rifiuta(): void
    {
        // Senza compressione un 1920×1080 a colori pesa circa 6 MB.
        $errori = FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Immagine, $this->png(1920, 1080, 2000), 'image/png');

        $this->assertCount(1, $errori);
        $this->assertStringContainsString('il massimo è 2,0 MB', $errori[0]);
    }

    #[Test]
    public function la_fascia_mobile_vuole_1080x300(): void
    {
        $this->assertSame([], FormatiDelleCreativita::erroriDellaFasciaMobile($this->png(1080, 300)));
        $this->assertNotSame([], FormatiDelleCreativita::erroriDellaFasciaMobile($this->png(1920, 270)));
    }

    #[Test]
    public function lo_spot_video_in_formato_va_bene(): void
    {
        $this->assertSame([], FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Video, $this->fixture('spot-1920x1080-2s.mp4'), 'video/mp4'));
    }

    #[Test]
    public function il_video_troppo_lungo_o_fuori_misura_si_rifiuta(): void
    {
        $this->assertSame(
            ['Il video dura 31 secondi: il massimo è 30.'],
            FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Video, $this->fixture('spot-1920x1080-31s-moov-in-fondo.mp4'), 'video/mp4'),
        );
        $this->assertSame(
            ['Il video è di 1280×720 px: deve essere di 1920×1080 px.'],
            FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Video, $this->fixture('spot-1280x720-muto.mp4'), 'video/mp4'),
        );
    }

    #[Test]
    public function un_immagine_caricata_come_video_si_rifiuta(): void
    {
        $this->assertSame(
            ['Il file non è un video MP4 leggibile.'],
            FormatiDelleCreativita::erroriDelContenuto(TipoCreativita::Video, $this->png(1920, 1080), 'image/png'),
        );
    }

    #[Test]
    public function la_durata_del_video_si_arrotonda_al_secondo(): void
    {
        $this->assertSame(2, FormatiDelleCreativita::durataDelVideo($this->fixture('spot-1920x1080-2s.mp4')));
    }

    #[Test]
    public function il_riquadro_cosa_serve_dice_misure_peso_e_durata(): void
    {
        $spot = array_column(FormatiDelleCreativita::requisiti(UsoCreativita::Preroll, TipoCreativita::Video), 1, 0);

        $this->assertSame('1920 × 1080 px (16:9, Full HD)', $spot['Misure']);
        $this->assertSame('20 MB', $spot['Peso massimo']);
        $this->assertSame('al massimo 30 secondi', $spot['Durata']);
        $this->assertArrayNotHasKey('Colonna a destra', $spot);
    }

    #[Test]
    public function per_la_cornice_il_riquadro_aggiunge_le_zone(): void
    {
        $cornice = array_column(FormatiDelleCreativita::requisiti(UsoCreativita::Cornice, TipoCreativita::Immagine), 1, 0);

        $this->assertSame('2 MB', $cornice['Peso massimo']);
        $this->assertSame('480 × 810 px', $cornice['Colonna a destra']);
        $this->assertStringContainsString('1440 × 810 px', $cornice['Coperta dalla diretta']);
        $this->assertStringContainsString('1080 × 300 px', $cornice['Fascia per il telefono']);
    }
}
