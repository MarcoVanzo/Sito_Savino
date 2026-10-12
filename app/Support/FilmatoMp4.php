<?php

namespace App\Support;

/**
 * Durata, dimensioni e codifiche di un file MP4, lette dall'intestazione.
 *
 * Le creatività sponsor della diretta hanno formati stretti (1920×1080, al
 * massimo 30 secondi, H.264/AAC) e vanno controllate al caricamento. `ffprobe`
 * non c'è sui container di App Platform e una libreria in più per leggere tre
 * numeri non vale la dipendenza: l'MP4 è una sequenza di «scatole» annidate
 * (ISO/IEC 14496-12) e quelle che servono sono poche.
 *
 * - `moov/mvhd`: scala dei tempi e durata del filmato;
 * - `moov/trak/tkhd`: larghezza e altezza della traccia (virgola fissa 16.16);
 * - `moov/trak/mdia/hdlr`: se la traccia è video (`vide`) o audio (`soun`);
 * - `moov/trak/mdia/minf/stbl/stsd`: la codifica (`avc1` = H.264, `mp4a` = AAC).
 *
 * Un file che non si riesce a leggere restituisce `null`: chi chiama lo
 * rifiuta con un messaggio, mai un'eccezione verso il pannello.
 */
class FilmatoMp4
{
    /** Oltre questa dimensione la scatola `moov` non è credibile. */
    private const MOOV_MASSIMO = 16 * 1024 * 1024;

    /**
     * @return array{durata: float, larghezza: int, altezza: int, video: ?string, audio: ?string}|null
     */
    public static function leggi(string $percorso): ?array
    {
        $moov = self::scatolaMoov($percorso);

        if ($moov === null) {
            return null;
        }

        $risultato = ['durata' => 0.0, 'larghezza' => 0, 'altezza' => 0, 'video' => null, 'audio' => null];
        $durataTrovata = false;

        foreach (self::figli($moov) as [$tipo, $corpo]) {
            if ($tipo === 'mvhd') {
                $durata = self::durataDaMvhd($corpo);

                if ($durata !== null) {
                    $risultato['durata'] = $durata;
                    $durataTrovata = true;
                }
            }

            if ($tipo === 'trak') {
                $traccia = self::traccia($corpo);

                if ($traccia['tipo'] === 'vide' && $risultato['video'] === null) {
                    $risultato['video'] = $traccia['codifica'];
                    $risultato['larghezza'] = $traccia['larghezza'];
                    $risultato['altezza'] = $traccia['altezza'];
                }

                if ($traccia['tipo'] === 'soun' && $risultato['audio'] === null) {
                    $risultato['audio'] = $traccia['codifica'];
                }
            }
        }

        return $durataTrovata ? $risultato : null;
    }

    /** La scatola `moov`, ovunque stia nel file (all'inizio o in fondo). */
    private static function scatolaMoov(string $percorso): ?string
    {
        $file = @fopen($percorso, 'rb');

        if ($file === false) {
            return null;
        }

        try {
            $dimensioneFile = (int) (fstat($file)['size'] ?? 0);
            $posizione = 0;

            while ($posizione + 8 <= $dimensioneFile) {
                fseek($file, $posizione);
                $testa = fread($file, 8);

                if ($testa === false || strlen($testa) < 8) {
                    return null;
                }

                $dimensione = unpack('N', substr($testa, 0, 4))[1];
                $tipo = substr($testa, 4, 4);
                $intestazione = 8;

                if ($dimensione === 1) {
                    $grande = fread($file, 8);

                    if ($grande === false || strlen($grande) < 8) {
                        return null;
                    }

                    $dimensione = unpack('J', $grande)[1];
                    $intestazione = 16;
                } elseif ($dimensione === 0) {
                    $dimensione = $dimensioneFile - $posizione;
                }

                if ($dimensione < $intestazione) {
                    return null;
                }

                if ($tipo === 'moov') {
                    $corpo = $dimensione - $intestazione;

                    if ($corpo > self::MOOV_MASSIMO) {
                        return null;
                    }

                    $dati = $corpo > 0 ? fread($file, $corpo) : '';

                    return $dati === false || strlen($dati) < $corpo ? null : $dati;
                }

                $posizione += $dimensione;
            }

            return null;
        } finally {
            fclose($file);
        }
    }

    /**
     * Le scatole contenute in un corpo: coppie [tipo, corpo].
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function figli(string $dati): array
    {
        $figli = [];
        $posizione = 0;
        $lunghezza = strlen($dati);

        while ($posizione + 8 <= $lunghezza) {
            $dimensione = unpack('N', substr($dati, $posizione, 4))[1];
            $tipo = substr($dati, $posizione + 4, 4);
            $intestazione = 8;

            if ($dimensione === 1 && $posizione + 16 <= $lunghezza) {
                $dimensione = unpack('J', substr($dati, $posizione + 8, 8))[1];
                $intestazione = 16;
            } elseif ($dimensione === 0) {
                $dimensione = $lunghezza - $posizione;
            }

            if ($dimensione < $intestazione || $posizione + $dimensione > $lunghezza) {
                break;
            }

            $figli[] = [$tipo, substr($dati, $posizione + $intestazione, $dimensione - $intestazione)];
            $posizione += $dimensione;
        }

        return $figli;
    }

    private static function durataDaMvhd(string $corpo): ?float
    {
        $versione = ord($corpo[0] ?? "\0");

        if ($versione === 1) {
            if (strlen($corpo) < 32) {
                return null;
            }

            $scala = unpack('N', substr($corpo, 20, 4))[1];
            $durata = unpack('J', substr($corpo, 24, 8))[1];
        } else {
            if (strlen($corpo) < 20) {
                return null;
            }

            $scala = unpack('N', substr($corpo, 12, 4))[1];
            $durata = unpack('N', substr($corpo, 16, 4))[1];
        }

        return $scala > 0 ? $durata / $scala : null;
    }

    /**
     * @return array{tipo: ?string, codifica: ?string, larghezza: int, altezza: int}
     */
    private static function traccia(string $trak): array
    {
        $traccia = ['tipo' => null, 'codifica' => null, 'larghezza' => 0, 'altezza' => 0];

        foreach (self::figli($trak) as [$tipo, $corpo]) {
            if ($tipo === 'tkhd') {
                // Larghezza e altezza sono le ultime due voci, in virgola fissa 16.16.
                if (strlen($corpo) >= 8) {
                    $traccia['larghezza'] = unpack('N', substr($corpo, -8, 4))[1] >> 16;
                    $traccia['altezza'] = unpack('N', substr($corpo, -4, 4))[1] >> 16;
                }
            }

            if ($tipo === 'mdia') {
                foreach (self::scatoleNelPercorso($corpo, ['hdlr']) as $hdlr) {
                    $traccia['tipo'] = strlen($hdlr) >= 12 ? substr($hdlr, 8, 4) : null;
                }

                foreach (self::scatoleNelPercorso($corpo, ['minf', 'stbl', 'stsd']) as $stsd) {
                    // versione e flag (4), numero di voci (4), poi la prima voce: dimensione (4) e formato (4).
                    $traccia['codifica'] = strlen($stsd) >= 16 ? substr($stsd, 12, 4) : null;
                }
            }
        }

        return $traccia;
    }

    /**
     * Le scatole che si trovano scendendo lungo un percorso di tipi.
     *
     * @param  list<string>  $percorso
     * @return list<string>
     */
    private static function scatoleNelPercorso(string $dati, array $percorso): array
    {
        $tipo = array_shift($percorso);
        $trovate = [];

        foreach (self::figli($dati) as [$figlio, $corpo]) {
            if ($figlio !== $tipo) {
                continue;
            }

            if ($percorso === []) {
                $trovate[] = $corpo;
            } else {
                array_push($trovate, ...self::scatoleNelPercorso($corpo, $percorso));
            }
        }

        return $trovate;
    }
}
