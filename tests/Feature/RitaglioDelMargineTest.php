<?php

namespace Tests\Feature;

use App\Support\RitaglioDelMargine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I loghi degli sponsor arrivavano dal vecchio sito dentro riquadri 600x400
 * con il marchio alto un terzo e il resto bianco: nella scheda il logo
 * sembrava minuscolo dentro una card grande. Il margine è dentro il file, e
 * l'unico modo di toglierlo è ritagliare.
 */
class RitaglioDelMargineTest extends TestCase
{
    /**
     * Immagine di prova: un rettangolo colorato dentro un fondo uniforme.
     */
    private function immagine(int $larghezza, int $altezza, array $fondo, ?array $contenuto = null): string
    {
        $tela = imagecreatetruecolor($larghezza, $altezza);
        imagefilledrectangle($tela, 0, 0, $larghezza, $altezza, imagecolorallocate($tela, ...$fondo));

        if ($contenuto !== null) {
            [$x1, $y1, $x2, $y2] = $contenuto;
            imagefilledrectangle($tela, $x1, $y1, $x2, $y2, imagecolorallocate($tela, 0, 48, 99));
        }

        ob_start();
        imagepng($tela);

        return (string) ob_get_clean();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensioni(string $byte): array
    {
        $misure = getimagesizefromstring($byte);

        return [$misure[0], $misure[1]];
    }

    #[Test]
    public function toglie_il_bianco_attorno_al_marchio(): void
    {
        // Il caso vero: marchio largo e basso dentro un riquadro 3:2.
        $originale = $this->immagine(600, 400, [255, 255, 255], [50, 170, 550, 230]);

        $ritagliato = RitaglioDelMargine::ritaglia($originale);

        $this->assertNotNull($ritagliato);
        [$larghezza, $altezza] = $this->dimensioni($ritagliato);

        $this->assertLessThan(600, $larghezza);
        $this->assertLessThan(120, $altezza);
        // Il marchio è largo circa otto volte l'altezza: dopo il ritaglio il
        // rapporto dell'immagine deve somigliare a quello del marchio.
        $this->assertGreaterThan(4, $larghezza / $altezza);
    }

    /** Un'immagine già senza margine non si tocca: il comando è ripetibile. */
    #[Test]
    public function un_immagine_gia_stretta_resta_com_e(): void
    {
        $originale = $this->immagine(400, 100, [255, 255, 255], [0, 0, 399, 99]);

        $this->assertNull(RitaglioDelMargine::ritaglia($originale));
    }

    /** Ritagliare due volte non toglie altro. */
    #[Test]
    public function ritagliare_due_volte_non_cambia_niente(): void
    {
        $originale = $this->immagine(600, 400, [255, 255, 255], [50, 170, 550, 230]);

        $primo = RitaglioDelMargine::ritaglia($originale);
        $this->assertNotNull($primo);

        $this->assertNull(RitaglioDelMargine::ritaglia($primo));
    }

    /**
     * Un marchio che arriva fino al bordo, o una foto: gli angoli non sono
     * d'accordo e non c'è nessun margine da togliere.
     */
    #[Test]
    public function senza_un_fondo_riconoscibile_non_ritaglia(): void
    {
        $tela = imagecreatetruecolor(200, 200);
        imagefilledrectangle($tela, 0, 0, 100, 200, imagecolorallocate($tela, 255, 255, 255));
        imagefilledrectangle($tela, 101, 0, 200, 200, imagecolorallocate($tela, 0, 0, 0));
        ob_start();
        imagepng($tela);
        $misto = (string) ob_get_clean();

        $this->assertNull(RitaglioDelMargine::ritaglia($misto));
    }

    /** Il fondo non è sempre bianco: un riquadro nero si comporta uguale. */
    #[Test]
    public function funziona_anche_su_fondo_scuro(): void
    {
        $originale = $this->immagine(600, 400, [0, 0, 0], [200, 180, 400, 220]);

        $ritagliato = RitaglioDelMargine::ritaglia($originale);

        $this->assertNotNull($ritagliato);
        [$larghezza] = $this->dimensioni($ritagliato);
        $this->assertLessThan(300, $larghezza);
    }

    /** Un file che non è un'immagine non deve far esplodere niente. */
    #[Test]
    public function un_file_illeggibile_non_rompe(): void
    {
        $this->assertNull(RitaglioDelMargine::ritaglia('questo non e un png'));
    }
}
