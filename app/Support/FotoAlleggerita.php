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
 * library: lato lungo al massimo 2560 px e JPEG a qualita' 86, a occhio
 * indistinguibili dall'originale. Il sito non mostra nulla oltre i 1200 px
 * (lo zoom del prodotto): una foto da fotocamera (6000 px, 15-30 MB) pesava
 * venti volte il necessario e superava il limite di upload (02/10/2026).
 *
 * Non si perde nulla di visibile:
 * - solo JPEG, cioe' le foto: PNG e WebP possono avere trasparenza, e il GD
 *   di Linux (produzione, CI) la perde nel ridimensionamento, mentre quello
 *   di macOS no. Un logo trasparente diventava nero;
 * - il profilo colore (ICC) si ricopia: GD lo scarta, e una foto Display P3
 *   (iPhone) o Adobe RGB uscirebbe spenta;
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
        $misure = @getimagesize($percorso);

        if ($misure === false || $misure[2] !== IMAGETYPE_JPEG) {
            return; // PNG, WebP, SVG, GIF, PDF, file illeggibili: si lasciano stare
        }

        [$larghezza, $altezza] = $misure;
        $daRimpicciolire = max($larghezza, $altezza) > $latoMassimo;
        $originale = (string) file_get_contents($percorso);
        $profilo = self::profiloColoreJpeg($originale);

        if (! $daRimpicciolire && strlen($originale) <= self::PESO_DA_RICOMPRIMERE) {
            return;
        }

        if (! self::memoriaSufficientePer($larghezza, $altezza)) {
            return;
        }

        $prova = $percorso.'.alleggerita.jpg';

        try {
            $immagine = Image::load($percorso); // load() raddrizza gia' secondo l'EXIF

            if ($daRimpicciolire) {
                $immagine->fit(Fit::Max, $latoMassimo, $latoMassimo);
            }

            $immagine->quality(self::QUALITA)->format('jpg')->save($prova);
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
     * Una copia della foto sotto `$byteMassimi`, rimpicciolita per gradi, o il
     * percorso originale se gia' ci sta (o non e' un JPEG). Chi la chiede
     * cancella la copia quando ha finito.
     */
    public static function copiaSotto(string $percorso, int $byteMassimi): string
    {
        if (@filesize($percorso) <= $byteMassimi || @getimagesize($percorso)[2] !== IMAGETYPE_JPEG) {
            return $percorso;
        }

        $copia = sys_get_temp_dir().'/'.uniqid('foto_sotto_').'.jpg';

        foreach ([4096, 3200, self::LATO_MASSIMO, 1920] as $lato) {
            copy($percorso, $copia);
            self::alleggerisci($copia, $lato);
            clearstatcache();

            if (filesize($copia) <= $byteMassimi) {
                break;
            }
        }

        return $copia;
    }

    /**
     * Toglie dai PNG il profilo colore (chunk iCCP) sul posto.
     *
     * Molti programmi esportano PNG con un profilo sRGB difettoso: sul GD di
     * Linux libpng avvisa ("iCCP: known incorrect sRGB profile"), Laravel fa
     * dell'avviso un'eccezione e la conversione della media library va in 500.
     * Cosi' falliva la foto prodotto da 700 KB della segreteria (02/10/2026);
     * su macOS l'avviso non arriva a PHP, quindi non si puo' rilevare caso per
     * caso. I pixel restano identici e le conversioni GD il profilo lo
     * scartano comunque.
     */
    public static function togliIlProfiloDalPng(string $percorso): bool
    {
        $png = @file_get_contents($percorso);
        $pulito = $png === false ? null : self::pngSenzaProfilo($png);

        return $pulito !== null && file_put_contents($percorso, $pulito) !== false;
    }

    /** Il PNG senza chunk iCCP, o null se non e' un PNG o non ne ha. */
    public static function pngSenzaProfilo(string $png): ?string
    {
        if (! str_starts_with($png, "\x89PNG\r\n\x1A\n")) {
            return null;
        }

        $risultato = substr($png, 0, 8);
        $posizione = 8;
        $tolti = 0;

        while ($posizione + 12 <= strlen($png)) {
            $lunghezza = unpack('N', substr($png, $posizione, 4))[1];
            $tipo = substr($png, $posizione + 4, 4);
            $chunk = substr($png, $posizione, $lunghezza + 12);

            if ($tipo === 'iCCP') {
                $tolti++;
            } else {
                $risultato .= $chunk;
            }

            $posizione += $lunghezza + 12;

            if ($tipo === 'IEND') {
                break;
            }
        }

        return $tolti > 0 ? $risultato : null;
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
}
