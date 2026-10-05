<?php

namespace App\Support;

use RuntimeException;
use Spatie\Image\Image;

/**
 * I volti piccoli di una foto, quasi sempre pubblico sullo sfondo, non si
 * confrontano con nessuno: l'informativa lo dice (parere del 5 ottobre 2026)
 * e questa classe è ciò che lo rende vero. Prima del riconoscimento i volti
 * sotto la soglia si coprono con un riquadro pieno, così CompreFace non li vede.
 *
 * La soglia è **relativa** al lato corto della foto
 * (`services.compreface.quota_volto_riconoscimento`), non in pixel: su una foto
 * da 24 MP anche un volto sfocato in tribuna supera i 100 px, e una soglia
 * fissa avrebbe lasciato passare proprio il pubblico. Essendo una proporzione,
 * vale uguale sulla foto ridotta che si manda a CompreFace.
 *
 * Si lavora su una copia raddrizzata secondo l'EXIF, sotto il peso massimo che
 * CompreFace accetta: rilevamento e riconoscimento devono vedere gli stessi
 * pixel, o i riquadri del primo cadrebbero altrove nel secondo.
 */
class VoltiDiSfondo
{
    /** Quanto si allarga il riquadro di un volto, per lato, prima di coprirlo. */
    private const MARGINE = 0.25;

    /**
     * Copia JPEG dritta della foto, sotto `$byteMassimi`. Chi la chiede la
     * cancella.
     *
     * @throws RuntimeException se la foto è illeggibile o la memoria non basta
     */
    public static function copiaDiLavoro(string $percorso, int $byteMassimi): string
    {
        [$larghezza, $altezza] = self::misure($percorso);

        if (! FotoAlleggerita::memoriaSufficientePer($larghezza, $altezza)) {
            throw new RuntimeException("Memoria insufficiente per la foto {$larghezza}x{$altezza}");
        }

        $copia = sys_get_temp_dir().'/'.uniqid('volti_').'.jpg';

        // load() raddrizza secondo l'EXIF; il JPEG salvato non lo porta piu'.
        Image::load($percorso)->quality(FotoAlleggerita::QUALITA)->format('jpg')->save($copia);
        self::portaSotto($copia, $byteMassimi);

        return $copia;
    }

    /** Lato corto della foto in pixel. */
    public static function latoCorto(string $percorso): int
    {
        return min(self::misure($percorso));
    }

    /** Altezza in pixel del riquadro di un volto come lo descrive CompreFace. */
    public static function altezza(array $volto): int
    {
        $box = $volto['box'] ?? [];

        return (int) (($box['y_max'] ?? 0) - ($box['y_min'] ?? 0));
    }

    public static function inPrimoPiano(array $volto, int $latoCorto): bool
    {
        return $latoCorto > 0
            && self::altezza($volto) / $latoCorto >= (float) config('services.compreface.quota_volto_riconoscimento', 0.04);
    }

    /**
     * Copre sul posto i volti di sfondo, allargati di un quarto per lato. I
     * volti in primo piano si rimettono com'erano dopo: un'atleta con una
     * testa piccola dietro la spalla non deve perdere mezzo viso.
     *
     * @param  array<int, array<string, mixed>>  $sfondo
     * @param  array<int, array<string, mixed>>  $primoPiano
     */
    public static function copri(string $copia, array $sfondo, array $primoPiano, int $byteMassimi): void
    {
        $immagine = @imagecreatefromjpeg($copia);

        if ($immagine === false) {
            throw new RuntimeException("Copia di lavoro illeggibile: {$copia}");
        }

        $intatti = [];
        foreach ($primoPiano as $volto) {
            $box = self::riquadro($volto, 0);
            $pezzo = imagecrop($immagine, ['x' => $box[0], 'y' => $box[1], 'width' => $box[2] - $box[0], 'height' => $box[3] - $box[1]]);
            if ($pezzo !== false) {
                $intatti[] = [$pezzo, $box];
            }
        }

        $grigio = (int) imagecolorallocate($immagine, 128, 128, 128);
        foreach ($sfondo as $volto) {
            imagefilledrectangle($immagine, ...[...self::riquadro($volto, self::MARGINE), $grigio]);
        }

        foreach ($intatti as [$pezzo, $box]) {
            imagecopy($immagine, $pezzo, $box[0], $box[1], 0, 0, imagesx($pezzo), imagesy($pezzo));
            imagedestroy($pezzo);
        }

        imagejpeg($immagine, $copia, FotoAlleggerita::QUALITA);
        imagedestroy($immagine);
        self::portaSotto($copia, $byteMassimi);
    }

    /**
     * Riquadro di un volto allargato di `$margine` per lato, non negativo.
     *
     * @return array{int, int, int, int}
     */
    private static function riquadro(array $volto, float $margine): array
    {
        $box = $volto['box'] ?? [];
        $larghezza = ($box['x_max'] ?? 0) - ($box['x_min'] ?? 0);
        $altezza = ($box['y_max'] ?? 0) - ($box['y_min'] ?? 0);

        return [
            max(0, (int) floor(($box['x_min'] ?? 0) - $larghezza * $margine)),
            max(0, (int) floor(($box['y_min'] ?? 0) - $altezza * $margine)),
            (int) ceil(($box['x_max'] ?? 0) + $larghezza * $margine),
            (int) ceil(($box['y_max'] ?? 0) + $altezza * $margine),
        ];
    }

    /**
     * Sopra il peso che CompreFace accetta si rimpicciolisce: la soglia è una
     * proporzione, quindi non cambia nulla per chi viene confrontato.
     */
    private static function portaSotto(string $copia, int $byteMassimi): void
    {
        clearstatcache();
        $ridotta = FotoAlleggerita::copiaSotto($copia, $byteMassimi);

        if ($ridotta !== $copia) {
            rename($ridotta, $copia);
        }
    }

    /** @return array{int, int} */
    private static function misure(string $percorso): array
    {
        $misure = @getimagesize($percorso);

        if ($misure === false) {
            throw new RuntimeException("Foto illeggibile: {$percorso}");
        }

        return [$misure[0], $misure[1]];
    }
}
