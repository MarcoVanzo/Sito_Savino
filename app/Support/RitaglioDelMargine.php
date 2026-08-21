<?php

namespace App\Support;

/**
 * Toglie il margine uniforme attorno a un'immagine.
 *
 * I loghi degli sponsor arrivano dal vecchio sito dentro riquadri 600x400 con
 * il marchio piccolo in mezzo: il marchio vero occupa un terzo dell'altezza e
 * il resto è bianco. Nella scheda l'immagine viene fatta stare per intero nel
 * riquadro (`object-contain`), quindi quel bianco viene mostrato insieme al
 * marchio e il logo sembra minuscolo dentro una card grande. Non è un problema
 * di CSS: è margine cotto dentro il file, e l'unico modo di toglierlo è
 * ritagliare.
 *
 * Il colore di fondo si legge dai quattro angoli: se non sono d'accordo — un
 * marchio che arriva fino al bordo, una foto — non c'è nessun margine da
 * togliere e l'immagine si lascia com'è.
 */
class RitaglioDelMargine
{
    /**
     * Quanto un pixel può discostarsi dal fondo restando "fondo": i JPEG hanno
     * artefatti di compressione e un bianco non è mai esattamente 255.
     */
    private const TOLLERANZA = 12;

    /**
     * Sotto questa quota di margine non vale la pena riscrivere il file.
     */
    private const MARGINE_MINIMO = 0.04;

    /**
     * Aria lasciata attorno al marchio, in quota sul lato ritagliato: un logo
     * appiccicato al bordo del riquadro sta peggio di uno un po' respirato.
     */
    private const ARIA = 0.02;

    /**
     * Ritaglia i byte di un'immagine, o restituisce null se non c'è margine da
     * togliere (o se il file non è un'immagine leggibile).
     */
    public static function ritaglia(string $byte): ?string
    {
        // Senza GD non si ritaglia niente, ma nemmeno si esplode: il file
        // resta com'e'. In produzione l'estensione e' mancata per settimane e
        // ogni conversione moriva con "undefined function".
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $immagine = @imagecreatefromstring($byte);

        if ($immagine === false) {
            return null;
        }

        $riquadro = self::riquadroDelContenuto($immagine);

        if ($riquadro === null) {
            return null;
        }

        [$sinistra, $alto, $destra, $basso] = $riquadro;

        $larghezza = imagesx($immagine);
        $altezza = imagesy($immagine);
        $margine = 1 - (($destra - $sinistra + 1) * ($basso - $alto + 1)) / ($larghezza * $altezza);

        if ($margine < self::MARGINE_MINIMO) {
            return null;
        }

        return self::disegnaIlRitaglio($immagine, $sinistra, $alto, $destra, $basso);
    }

    /**
     * Riquadro del contenuto vero, o null se non c'è un fondo riconoscibile.
     *
     * @param  \GdImage  $immagine
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    private static function riquadroDelContenuto($immagine): ?array
    {
        $larghezza = imagesx($immagine);
        $altezza = imagesy($immagine);

        $fondo = self::coloreDiFondo($immagine, $larghezza, $altezza);

        if ($fondo === null) {
            return null;
        }

        $sinistra = $larghezza;
        $destra = -1;
        $alto = $altezza;
        $basso = -1;

        for ($y = 0; $y < $altezza; $y++) {
            for ($x = 0; $x < $larghezza; $x++) {
                if (self::eFondo($immagine, $x, $y, $fondo)) {
                    continue;
                }

                if ($x < $sinistra) {
                    $sinistra = $x;
                }
                if ($x > $destra) {
                    $destra = $x;
                }
                if ($y < $alto) {
                    $alto = $y;
                }
                if ($y > $basso) {
                    $basso = $y;
                }
            }
        }

        // Immagine tutta di un colore: non c'è niente da ritagliare.
        if ($destra < $sinistra || $basso < $alto) {
            return null;
        }

        $ariaX = (int) round(($destra - $sinistra + 1) * self::ARIA);
        $ariaY = (int) round(($basso - $alto + 1) * self::ARIA);

        return [
            max(0, $sinistra - $ariaX),
            max(0, $alto - $ariaY),
            min($larghezza - 1, $destra + $ariaX),
            min($altezza - 1, $basso + $ariaY),
        ];
    }

    /**
     * Il colore di fondo, se i quattro angoli sono d'accordo.
     *
     * @param  \GdImage  $immagine
     * @return array{r: int, g: int, b: int, a: int}|null
     */
    private static function coloreDiFondo($immagine, int $larghezza, int $altezza): ?array
    {
        $angoli = [
            [0, 0],
            [$larghezza - 1, 0],
            [0, $altezza - 1],
            [$larghezza - 1, $altezza - 1],
        ];

        $primo = self::pixel($immagine, $angoli[0][0], $angoli[0][1]);

        foreach ($angoli as [$x, $y]) {
            if (! self::simili($primo, self::pixel($immagine, $x, $y))) {
                return null;
            }
        }

        return $primo;
    }

    /**
     * @param  \GdImage  $immagine
     * @return array{r: int, g: int, b: int, a: int}
     */
    private static function pixel($immagine, int $x, int $y): array
    {
        $colore = imagecolorat($immagine, $x, $y);

        return [
            'r' => ($colore >> 16) & 0xFF,
            'g' => ($colore >> 8) & 0xFF,
            'b' => $colore & 0xFF,
            // GD tiene l'alpha su 7 bit: 0 opaco, 127 trasparente.
            'a' => ($colore >> 24) & 0x7F,
        ];
    }

    /**
     * @param  array{r: int, g: int, b: int, a: int}  $uno
     * @param  array{r: int, g: int, b: int, a: int}  $altro
     */
    private static function simili(array $uno, array $altro): bool
    {
        // Due pixel entrambi trasparenti sono lo stesso fondo, qualunque
        // colore ci sia sotto.
        if ($uno['a'] > 100 && $altro['a'] > 100) {
            return true;
        }

        return abs($uno['r'] - $altro['r']) <= self::TOLLERANZA
            && abs($uno['g'] - $altro['g']) <= self::TOLLERANZA
            && abs($uno['b'] - $altro['b']) <= self::TOLLERANZA
            && abs($uno['a'] - $altro['a']) <= self::TOLLERANZA;
    }

    /**
     * @param  \GdImage  $immagine
     * @param  array{r: int, g: int, b: int, a: int}  $fondo
     */
    private static function eFondo($immagine, int $x, int $y, array $fondo): bool
    {
        return self::simili($fondo, self::pixel($immagine, $x, $y));
    }

    /**
     * @param  \GdImage  $immagine
     */
    private static function disegnaIlRitaglio($immagine, int $sinistra, int $alto, int $destra, int $basso): ?string
    {
        $larghezza = $destra - $sinistra + 1;
        $altezza = $basso - $alto + 1;

        $ritagliata = imagecreatetruecolor($larghezza, $altezza);

        if ($ritagliata === false) {
            return null;
        }

        // La trasparenza va conservata: un PNG con il fondo trasparente
        // ritagliato su fondo nero diventerebbe illeggibile sul bianco.
        imagealphablending($ritagliata, false);
        imagesavealpha($ritagliata, true);
        $trasparente = imagecolorallocatealpha($ritagliata, 0, 0, 0, 127);
        imagefilledrectangle($ritagliata, 0, 0, $larghezza, $altezza, $trasparente);

        imagecopy($ritagliata, $immagine, 0, 0, $sinistra, $alto, $larghezza, $altezza);

        ob_start();
        imagepng($ritagliata, null, 6);

        return ob_get_clean() ?: null;
    }
}
