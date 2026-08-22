<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Indirizzo pubblico di un file caricato dal pannello dentro `content_data`
 * (press kit, magazine, immagini dei pulsanti).
 *
 * I campi di upload salvano il percorso relativo al disco configurato. In
 * locale quel disco è `local` e il file si raggiunge sotto `/storage`, in
 * produzione è Spaces e l'indirizzo è su un altro dominio: comporre
 * "/storage/{percorso}" nel template funzionava solo sul portatile.
 */
class CmsFile
{
    /** Campi di primo livello che contengono un file caricato dal pannello. */
    private const CAMPI_SEMPLICI = ['button_image'];

    /**
     * Campi file dentro gli elenchi ripetibili, per elenco.
     *
     * @var array<string, array<int, string>>
     */
    private const CAMPI_NEGLI_ELENCHI = [
        'magazines' => ['file_url', 'cover_image_url'],
        'documents' => ['file'],
        'press_kits' => ['file'],
    ];

    public static function url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '' || trim($path) === '#') {
            return null;
        }

        $path = trim($path);

        // Un indirizzo già completo (o una risorsa già pubblica) si lascia com'è.
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::url($path);
    }

    /**
     * Riscrive dentro `content_data` i percorsi dei file caricati dal pannello.
     *
     * @param  array<string, mixed>  $contentData
     * @return array<string, mixed>
     */
    public static function resolveInContentData(array $contentData): array
    {
        foreach (self::CAMPI_SEMPLICI as $campo) {
            if (isset($contentData[$campo])) {
                $contentData[$campo] = self::url($contentData[$campo]);
            }
        }

        foreach (self::CAMPI_NEGLI_ELENCHI as $elenco => $campi) {
            if (! is_array($contentData[$elenco] ?? null)) {
                continue;
            }

            $contentData[$elenco] = array_map(
                fn ($voce) => is_array($voce) ? self::risolviLaVoce($voce, $campi) : $voce,
                $contentData[$elenco]
            );
        }

        return $contentData;
    }

    /**
     * Riscrive i campi file di una voce d'elenco (un numero del magazine, un
     * documento, una cartella stampa).
     *
     * @param  array<string, mixed>  $voce
     * @param  array<int, string>  $campi
     * @return array<string, mixed>
     */
    private static function risolviLaVoce(array $voce, array $campi): array
    {
        foreach ($campi as $campo) {
            if (isset($voce[$campo])) {
                $voce[$campo] = self::url($voce[$campo]);
            }
        }

        return $voce;
    }
}
