<?php

namespace App\Support\Diretta;

use App\Models\SiteSetting;

/**
 * Le impostazioni globali della diretta con sponsor (gruppo `diretta`).
 *
 * L'interruttore generale decide se la funzione esiste: spento, il sito mostra
 * la diretta come prima, senza pre-roll né cornici, e nessuna rotta degli
 * sponsor risponde. Gli altri valori sono quelli con cui nasce la diretta di
 * una gara; la redazione li cambia poi gara per gara.
 */
class ImpostazioniDellaDiretta
{
    public const PRIMA_DI_SALTA_MASSIMO = 15;

    public const CICLO_OGNI_MINUTI_MINIMO = 3;

    public const CICLO_OGNI_MINUTI_MASSIMO = 30;

    public const CICLO_DURATA_MINIMA = 5;

    public const CICLO_DURATA_MASSIMA = 30;

    public const RITARDO_MASSIMO_MINUTI = 60;

    public const PAUSA_DURATA_MINIMA = 10;

    public const PAUSA_DURATA_MASSIMA = 900;

    /** Valori di partenza, usati anche quando la riga non esiste. */
    public const PREDEFINITI = [
        'diretta_sponsor_abilitata' => false,
        'diretta_preroll_salta_dopo_secondi' => 5,
        'diretta_ciclo_attivo' => true,
        'diretta_ciclo_ogni_minuti' => 10,
        'diretta_ciclo_durata_secondi' => 15,
        'diretta_ciclo_ritardo_minuti' => 5,
        'diretta_pausa_durata_secondi' => 180,
        'diretta_apertura_automatica' => false,
    ];

    public static function abilitata(): bool
    {
        return filter_var(
            SiteSetting::get('diretta_sponsor_abilitata', self::PREDEFINITI['diretta_sponsor_abilitata']),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * La home apre da sola la diretta della gara in corso (`MatchDay`).
     * Non dipende dagli sponsor: vale anche con l'interruttore generale spento.
     */
    public static function aperturaAutomatica(): bool
    {
        return filter_var(
            SiteSetting::get('diretta_apertura_automatica', self::PREDEFINITI['diretta_apertura_automatica']),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * I valori con cui nasce la diretta di una gara, già nei limiti.
     *
     * @return array{preroll_salta_dopo_secondi: int, ciclo_attivo: bool, ciclo_ogni_minuti: int, ciclo_durata_secondi: int, ciclo_ritardo_minuti: int, pausa_durata_predefinita_secondi: int}
     */
    public static function perUnaGaraNuova(): array
    {
        return [
            'preroll_salta_dopo_secondi' => self::intero('diretta_preroll_salta_dopo_secondi', 0, self::PRIMA_DI_SALTA_MASSIMO),
            'ciclo_attivo' => filter_var(
                SiteSetting::get('diretta_ciclo_attivo', self::PREDEFINITI['diretta_ciclo_attivo']),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'ciclo_ogni_minuti' => self::intero('diretta_ciclo_ogni_minuti', self::CICLO_OGNI_MINUTI_MINIMO, self::CICLO_OGNI_MINUTI_MASSIMO),
            'ciclo_durata_secondi' => self::intero('diretta_ciclo_durata_secondi', self::CICLO_DURATA_MINIMA, self::CICLO_DURATA_MASSIMA),
            'ciclo_ritardo_minuti' => self::intero('diretta_ciclo_ritardo_minuti', 0, self::RITARDO_MASSIMO_MINUTI),
            'pausa_durata_predefinita_secondi' => self::intero('diretta_pausa_durata_secondi', self::PAUSA_DURATA_MINIMA, self::PAUSA_DURATA_MASSIMA),
        ];
    }

    private static function intero(string $chiave, int $minimo, int $massimo): int
    {
        $valore = SiteSetting::get($chiave);
        $valore = is_numeric($valore) ? (int) $valore : self::PREDEFINITI[$chiave];

        return max($minimo, min($massimo, $valore));
    }
}
