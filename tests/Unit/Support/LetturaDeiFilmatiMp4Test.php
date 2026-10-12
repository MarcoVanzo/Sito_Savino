<?php

namespace Tests\Unit\Support;

use App\Support\FilmatoMp4;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Durata, misure e codifiche lette dall'intestazione degli MP4, senza ffprobe
 * (non c'è sui container di App Platform). File veri, prodotti con ffmpeg.
 */
class LetturaDeiFilmatiMp4Test extends TestCase
{
    private function fixture(string $nome): string
    {
        return dirname(__DIR__, 2).'/Fixtures/Diretta/'.$nome;
    }

    #[Test]
    public function legge_misure_durata_e_codifiche_di_uno_spot_h264_aac(): void
    {
        $this->assertSame([
            'durata' => 2.0,
            'larghezza' => 1920,
            'altezza' => 1080,
            'video' => 'avc1',
            'audio' => 'mp4a',
        ], FilmatoMp4::leggi($this->fixture('spot-1920x1080-2s.mp4')));
    }

    #[Test]
    public function un_video_senza_audio_non_ha_codifica_audio(): void
    {
        $filmato = FilmatoMp4::leggi($this->fixture('spot-1280x720-muto.mp4'));

        $this->assertSame(1280, $filmato['larghezza']);
        $this->assertSame(720, $filmato['altezza']);
        $this->assertNull($filmato['audio']);
    }

    /** Senza `+faststart` l'intestazione sta in fondo, dopo i dati. */
    #[Test]
    public function trova_l_intestazione_anche_in_fondo_al_file(): void
    {
        $this->assertSame(31.0, FilmatoMp4::leggi($this->fixture('spot-1920x1080-31s-moov-in-fondo.mp4'))['durata']);
    }

    #[Test]
    public function un_file_che_non_e_un_mp4_non_si_legge(): void
    {
        $percorso = tempnam(sys_get_temp_dir(), 'mp4');
        file_put_contents($percorso, str_repeat('non sono un video', 100));

        try {
            $this->assertNull(FilmatoMp4::leggi($percorso));
        } finally {
            unlink($percorso);
        }
    }
}
