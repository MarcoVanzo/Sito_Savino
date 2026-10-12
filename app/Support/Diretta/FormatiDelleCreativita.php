<?php

namespace App\Support\Diretta;

use App\Enums\TipoCreativita;
use App\Enums\UsoCreativita;
use App\Support\FilmatoMp4;

/**
 * I formati che una creatività sponsor della diretta deve rispettare.
 *
 * Le misure sono quelle del riquadro 16:9 della diretta: il pre-roll lo occupa
 * tutto, la cornice ci sta sotto con il player che ne copre l'area in alto a
 * sinistra (1440×810 su 1920×1080, cioè il 75%), la fascia per il telefono sta
 * sotto il player. Un file fuori misura verrebbe tagliato o deformato davanti
 * allo sponsor: si rifiuta al caricamento, dicendo cosa non va.
 */
class FormatiDelleCreativita
{
    public const LARGHEZZA = 1920;

    public const ALTEZZA = 1080;

    public const FASCIA_MOBILE_LARGHEZZA = 1080;

    public const FASCIA_MOBILE_ALTEZZA = 300;

    public const DURATA_MASSIMA_SECONDI = 30;

    public const PESO_MASSIMO_VIDEO_KB = 20 * 1024;

    public const PESO_MASSIMO_IMMAGINE_KB = 2 * 1024;

    public const PESO_MASSIMO_FASCIA_MOBILE_KB = 1024;

    /** Durata predefinita di un'immagine. */
    public const DURATA_IMMAGINE_SECONDI = 10;

    /** @var list<string> */
    public const MIME_IMMAGINE = ['image/jpeg', 'image/png'];

    /**
     * `application/mp4` è lo stesso file con l'etichetta che alcuni sistemi
     * danno agli MP4: il contenuto lo verifica comunque FilmatoMp4.
     *
     * @var list<string>
     */
    public const MIME_VIDEO = ['video/mp4', 'application/mp4'];

    /** Codifiche ammesse: H.264 (`avc1`/`avc3`) per il video, AAC per l'audio. */
    private const CODIFICHE_VIDEO = ['avc1', 'avc3'];

    private const CODIFICHE_AUDIO = ['mp4a'];

    /** @return list<string> */
    public static function mimeAmmessi(TipoCreativita $tipo): array
    {
        return $tipo === TipoCreativita::Video ? self::MIME_VIDEO : self::MIME_IMMAGINE;
    }

    public static function pesoMassimoKb(TipoCreativita $tipo): int
    {
        return $tipo === TipoCreativita::Video ? self::PESO_MASSIMO_VIDEO_KB : self::PESO_MASSIMO_IMMAGINE_KB;
    }

    /** Il testo d'aiuto del campo di caricamento. */
    public static function descrizione(?UsoCreativita $uso, ?TipoCreativita $tipo): string
    {
        $video = $tipo === TipoCreativita::Video;
        $base = $video
            ? 'MP4 H.264/AAC, 1920×1080 px, al massimo 30 secondi e 20 MB.'
            : 'JPG o PNG, 1920×1080 px, al massimo 2 MB.';

        return match ($uso) {
            UsoCreativita::Cornice => $base.' L\'area 1440×810 in alto a sinistra resta coperta dal player: riempirla di un colore neutro. Restano visibili la colonna a destra (480×810) e la fascia sotto (1920×270).'
                .($video ? ' Il video va in onda senza audio.' : ''),
            default => $base,
        };
    }

    /**
     * Cosa non va nel file del contenuto principale; elenco vuoto = va bene.
     * Il tipo dell'immagine si legge dal contenuto, non dall'etichetta.
     *
     * @return list<string>
     */
    public static function erroriDelContenuto(TipoCreativita $tipo, string $percorso, ?string $mime): array
    {
        if ($tipo === TipoCreativita::Immagine) {
            return self::erroriDellImmagine($percorso, self::LARGHEZZA, self::ALTEZZA, self::PESO_MASSIMO_IMMAGINE_KB);
        }

        return self::erroriDelVideo($percorso, $mime);
    }

    /** @return list<string> */
    public static function erroriDellaFasciaMobile(string $percorso): array
    {
        return self::erroriDellImmagine(
            $percorso,
            self::FASCIA_MOBILE_LARGHEZZA,
            self::FASCIA_MOBILE_ALTEZZA,
            self::PESO_MASSIMO_FASCIA_MOBILE_KB,
        );
    }

    /** La durata del video arrotondata al secondo, se si riesce a leggere. */
    public static function durataDelVideo(string $percorso): ?int
    {
        $filmato = FilmatoMp4::leggi($percorso);

        return $filmato === null ? null : (int) ceil($filmato['durata']);
    }

    /** @return list<string> */
    private static function erroriDellImmagine(string $percorso, int $larghezza, int $altezza, int $pesoKb): array
    {
        $errori = [];
        $misure = @getimagesize($percorso);

        if ($misure === false || ! in_array($misure['mime'], self::MIME_IMMAGINE, true)) {
            return ['Il file non è un\'immagine JPG o PNG.'];
        }

        if ($misure[0] !== $larghezza || $misure[1] !== $altezza) {
            $errori[] = sprintf('L\'immagine è di %d×%d px: deve essere di %d×%d px.', $misure[0], $misure[1], $larghezza, $altezza);
        }

        $peso = (int) @filesize($percorso);

        if ($peso > $pesoKb * 1024) {
            $errori[] = sprintf('L\'immagine pesa %s: il massimo è %s.', self::peso($peso), self::peso($pesoKb * 1024));
        }

        return $errori;
    }

    /** @return list<string> */
    private static function erroriDelVideo(string $percorso, ?string $mime): array
    {
        $filmato = FilmatoMp4::leggi($percorso);

        if ($filmato === null || ($mime !== null && ! in_array($mime, self::MIME_VIDEO, true))) {
            return ['Il file non è un video MP4 leggibile.'];
        }

        $errori = [];

        if (! in_array($filmato['video'], self::CODIFICHE_VIDEO, true)) {
            $errori[] = 'Il video non è codificato in H.264.';
        }

        if ($filmato['audio'] !== null && ! in_array($filmato['audio'], self::CODIFICHE_AUDIO, true)) {
            $errori[] = 'L\'audio non è codificato in AAC.';
        }

        if ($filmato['larghezza'] !== self::LARGHEZZA || $filmato['altezza'] !== self::ALTEZZA) {
            $errori[] = sprintf('Il video è di %d×%d px: deve essere di %d×%d px.', $filmato['larghezza'], $filmato['altezza'], self::LARGHEZZA, self::ALTEZZA);
        }

        // Mezzo secondo di tolleranza: un montaggio da 30 s esce spesso a 30,02.
        if ($filmato['durata'] > self::DURATA_MASSIMA_SECONDI + 0.5) {
            $errori[] = sprintf('Il video dura %d secondi: il massimo è %d.', (int) ceil($filmato['durata']), self::DURATA_MASSIMA_SECONDI);
        }

        $peso = (int) @filesize($percorso);

        if ($peso > self::PESO_MASSIMO_VIDEO_KB * 1024) {
            $errori[] = sprintf('Il video pesa %s: il massimo è %s.', self::peso($peso), self::peso(self::PESO_MASSIMO_VIDEO_KB * 1024));
        }

        return $errori;
    }

    private static function peso(int $byte): string
    {
        return $byte >= 1024 * 1024
            ? number_format($byte / 1024 / 1024, 1, ',', '.').' MB'
            : number_format($byte / 1024, 0, ',', '.').' KB';
    }
}
