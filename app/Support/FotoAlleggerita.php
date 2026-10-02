<?php

namespace App\Support;

use App\Models\GalleryImage;
use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Alleggerisce le foto caricate dal pannello prima che entrino nella media
 * library: lato lungo al massimo 2560 px e JPEG/WebP a qualita' 86, a occhio
 * indistinguibili dall'originale. Il sito non mostra nulla oltre i 1200 px
 * (lo zoom del prodotto): una foto da fotocamera (6000 px, 15-30 MB) pesava
 * venti volte il necessario e superava il limite di upload (02/10/2026).
 *
 * Non si perde nulla di visibile:
 * - il profilo colore (ICC) dei JPEG si ricopia: GD lo scarta, e una foto
 *   Display P3 (iPhone) o Adobe RGB uscirebbe spenta. PNG e WebP con un
 *   profilo restano come sono;
 * - una foto che non va rimpicciolita e pesa poco non si ricodifica;
 * - se il risultato non e' piu' leggero si tiene l'originale.
 *
 * Mai un errore verso chi carica: se la lettura fallisce o la memoria non
 * basta il file resta com'e'.
 */
class FotoAlleggerita
{
    public const LATO_MASSIMO = 2560;

    public const QUALITA = 86;

    /** Sotto questa soglia una foto che non va rimpicciolita non si ricodifica. */
    public const PESO_DA_RICOMPRIMERE = 2 * 1024 * 1024;

    /** Tetto a cui si puo' alzare memory_limit per una foto grande. */
    private const MEMORIA_MASSIMA = 640 * 1024 * 1024;

    /**
     * Lato massimo per modello; `null` = non toccare. La gallery resta
     * originale: CompreFace analizza quel file e un volto da 90 px su 6000
     * diventerebbe di 38 px, sotto `services.compreface.min_face_px`.
     *
     * @var array<class-string<Model>, int|null>
     */
    private const ECCEZIONI = [
        GalleryImage::class => null,
        HeroSlide::class => 3840, // testata a tutta larghezza sugli schermi 4K
    ];

    private const FORMATI = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function latoMassimoPer(?Model $record): ?int
    {
        foreach (self::ECCEZIONI as $classe => $lato) {
            if ($record instanceof $classe) {
                return $lato;
            }
        }

        return self::LATO_MASSIMO;
    }

    /** Riscrive il file sul posto se ne vale la pena. */
    public static function alleggerisci(string $percorso, int $latoMassimo = self::LATO_MASSIMO): void
    {
        $formato = self::FORMATI[@mime_content_type($percorso) ?: ''] ?? null;
        $misure = @getimagesize($percorso);

        if ($formato === null || $misure === false) {
            return; // SVG, GIF animate, PDF, file illeggibili: si lasciano stare
        }

        [$larghezza, $altezza] = $misure;
        $daRimpicciolire = max($larghezza, $altezza) > $latoMassimo;
        $originale = (string) file_get_contents($percorso);
        $profilo = $formato === 'jpg' ? self::profiloColoreJpeg($originale) : null;

        if (! $daRimpicciolire && strlen($originale) <= self::PESO_DA_RICOMPRIMERE) {
            return;
        }

        if ($formato !== 'jpg' && self::haUnProfiloColore($originale, $formato)) {
            return;
        }

        if (! self::memoriaSufficientePer($larghezza, $altezza)) {
            return;
        }

        $prova = $percorso.'.alleggerita.'.$formato;

        try {
            $immagine = Image::load($percorso); // load() raddrizza gia' secondo l'EXIF

            if ($daRimpicciolire) {
                $immagine->fit(Fit::Max, $latoMassimo, $latoMassimo);
            }

            if ($formato !== 'png') {
                $immagine->quality(self::QUALITA);
            }

            $immagine->format($formato)->save($prova);
            unset($immagine);

            $risultato = (string) file_get_contents($prova);
            if ($profilo !== null) {
                $risultato = self::conIlProfiloColore($risultato, $profilo);
            }

            if (strlen($risultato) < strlen($originale)) {
                file_put_contents($percorso, $risultato);
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            if (is_file($prova)) {
                @unlink($prova);
            }
        }
    }

    /**
     * GD tiene in memoria 4 byte per pixel, piu' la copia rimpicciolita: una
     * foto da 45 MP chiede ~200 MB. Un memory_limit superato e' un errore
     * fatale (500), non un'eccezione: se serve si alza per questa richiesta,
     * fino a un tetto, altrimenti si rinuncia.
     */
    private static function memoriaSufficientePer(int $larghezza, int $altezza): bool
    {
        $limite = self::inByte((string) ini_get('memory_limit'));

        if ($limite <= 0) {
            return true; // nessun limite
        }

        $serve = memory_get_usage() + (int) ($larghezza * $altezza * 4 * 1.3) + 48 * 1024 * 1024;

        if ($serve <= $limite) {
            return true;
        }

        return $serve <= self::MEMORIA_MASSIMA && ini_set('memory_limit', (string) $serve) !== false;
    }

    private static function inByte(string $valore): int
    {
        $valore = trim($valore);
        $numero = (int) $valore;

        return match (strtolower(substr($valore, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    /**
     * I segmenti APP2 "ICC_PROFILE" di un JPEG, nell'ordine, o null.
     */
    private static function profiloColoreJpeg(string $jpeg): ?string
    {
        $segmenti = '';
        $posizione = 2; // dopo SOI
        $lunghezza = strlen($jpeg);

        while ($posizione + 4 <= $lunghezza && $jpeg[$posizione] === "\xFF") {
            $marcatore = ord($jpeg[$posizione + 1]);

            if ($marcatore === 0xDA || $marcatore === 0xD9) {
                break; // inizio dei dati dell'immagine
            }

            $dimensione = unpack('n', substr($jpeg, $posizione + 2, 2))[1];
            $segmento = substr($jpeg, $posizione, $dimensione + 2);

            if ($marcatore === 0xE2 && substr($segmento, 4, 12) === "ICC_PROFILE\0") {
                $segmenti .= $segmento;
            }

            $posizione += $dimensione + 2;
        }

        return $segmenti === '' ? null : $segmenti;
    }

    /** Inserisce i segmenti ICC subito dopo l'intestazione JFIF (o SOI). */
    private static function conIlProfiloColore(string $jpeg, string $profilo): string
    {
        $dopo = 2;

        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            $dopo += unpack('n', substr($jpeg, 4, 2))[1] + 2;
        }

        return substr($jpeg, 0, $dopo).$profilo.substr($jpeg, $dopo);
    }

    private static function haUnProfiloColore(string $contenuto, string $formato): bool
    {
        $intestazione = substr($contenuto, 0, 64 * 1024);

        return match ($formato) {
            'png' => str_contains($intestazione, 'iCCP'),
            'webp' => str_contains($intestazione, 'ICCP'),
            default => false,
        };
    }
}
