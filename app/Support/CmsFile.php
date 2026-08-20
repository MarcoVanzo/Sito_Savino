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
        $campiSemplici = [
            'button_image',
            'press_kit_1_file', 'press_kit_2_file', 'press_kit_3_file', 'press_kit_4_file',
        ];

        foreach ($campiSemplici as $campo) {
            if (isset($contentData[$campo])) {
                $contentData[$campo] = self::url($contentData[$campo]);
            }
        }

        $campiNegliElenchi = [
            'magazines' => ['file_url', 'cover_image_url'],
            'documents' => ['file'],
        ];

        foreach ($campiNegliElenchi as $elenco => $campi) {
            if (! is_array($contentData[$elenco] ?? null)) {
                continue;
            }

            $contentData[$elenco] = array_map(function ($voce) use ($campi) {
                if (! is_array($voce)) {
                    return $voce;
                }

                foreach ($campi as $campo) {
                    if (isset($voce[$campo])) {
                        $voce[$campo] = self::url($voce[$campo]);
                    }
                }

                return $voce;
            }, $contentData[$elenco]);
        }

        return $contentData;
    }
}
