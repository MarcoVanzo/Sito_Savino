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
 * - `moov/mvex/mehd`: la durata degli MP4 frammentati (OBS, `ffmpeg
 *   -movflags frag_keyframe`), che in `mvhd` hanno solo il primo pezzo;
 * - `moov/trak/tkhd`: larghezza e altezza della traccia (virgola fissa 16.16)
 *   e la matrice di trasformazione (un video girato in verticale col
 *   telefono dichiara 1920×1080 e una rotazione);
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
     * @return array{durata: float, larghezza: int, altezza: int, ruotato: bool, video: ?string, audio: ?string}|null
     */
    public static function leggi(string $percorso): ?array
    {
        $moov = self::scatolaMoov($percorso);

        if ($moov === null) {
            return null;
        }

        $risultato = ['durata' => 0.0, 'larghezza' => 0, 'altezza' => 0, 'ruotato' => false, 'video' => null, 'audio' => null];
        $durataTrovata = false;
        $scala = 0;

        foreach (self::figli($moov) as [$tipo, $corpo]) {
            if ($tipo === 'mvhd') {
                $tempi = self::tempiDaMvhd($corpo);

                if ($tempi !== null) {
                    [$scala, $durata] = $tempi;
                    $risultato['durata'] = (float) ($durata / $scala);
                    $durataTrovata = true;
                }
            }

            if ($tipo === 'trak') {
                $traccia = self::traccia($corpo);

                if ($traccia['tipo'] === 'vide' && $risultato['video'] === null) {
                    $risultato['video'] = $traccia['codifica'];
                    $risultato['larghezza'] = $traccia['larghezza'];
                    $risultato['altezza'] = $traccia['altezza'];
                    $risultato['ruotato'] = $traccia['ruotato'];
                }

                if ($traccia['tipo'] === 'soun' && $risultato['audio'] === null) {
                    $risultato['audio'] = $traccia['codifica'];
                }
            }
        }

        // Frammentato (c'è `mvex`): `mvhd` dice solo la parte descritta in
        // `moov`, il resto sta nei frammenti (un file da 40 s ne dichiarava
        // 10). Vale la durata di `mehd`; senza, la durata è ignota (0) e chi
        // chiama rifiuta il file.
        if ($durataTrovata && self::scatoleNelPercorso($moov, ['mvex']) !== []) {
            $risultato['durata'] = 0.0;

            foreach (self::scatoleNelPercorso($moov, ['mvex', 'mehd']) as $mehd) {
                $frammenti = self::durataDaMehd($mehd);

                if ($frammenti !== null) {
                    $risultato['durata'] = (float) ($frammenti / $scala);
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

    /**
     * Scala dei tempi (positiva) e durata nella stessa unità.
     *
     * @return array{0: int, 1: int}|null
     */
    private static function tempiDaMvhd(string $corpo): ?array
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

        return $scala > 0 ? [$scala, $durata] : null;
    }

    private static function durataDaMehd(string $corpo): ?int
    {
        $versione = ord($corpo[0] ?? "\0");

        if ($versione === 1) {
            return strlen($corpo) >= 12 ? unpack('J', substr($corpo, 4, 8))[1] : null;
        }

        return strlen($corpo) >= 8 ? unpack('N', substr($corpo, 4, 4))[1] : null;
    }

    /**
     * @return array{tipo: ?string, codifica: ?string, larghezza: int, altezza: int, ruotato: bool}
     */
    private static function traccia(string $trak): array
    {
        $traccia = ['tipo' => null, 'codifica' => null, 'larghezza' => 0, 'altezza' => 0, 'ruotato' => false];

        foreach (self::figli($trak) as [$tipo, $corpo]) {
            if ($tipo === 'tkhd') {
                // Larghezza e altezza sono le ultime due voci, in virgola fissa 16.16.
                if (strlen($corpo) >= 8) {
                    $traccia['larghezza'] = unpack('N', substr($corpo, -8, 4))[1] >> 16;
                    $traccia['altezza'] = unpack('N', substr($corpo, -4, 4))[1] >> 16;
                }

                // Prima delle misure, la matrice 3×3: a, b, u, c, d, v, x, y, w.
                // Senza rotazione né ribaltamento a = d = 1.0 e b = c = 0.
                if (strlen($corpo) >= 44) {
                    [, $a, $b, , $c, $d] = unpack('N5', substr($corpo, -44, 20));
                    $traccia['ruotato'] = ! ($a === 0x00010000 && $b === 0 && $c === 0 && $d === 0x00010000);
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
