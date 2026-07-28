<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Recupera i loghi squadra caricati prima dell'integrazione con la Lega.
 *
 * Il vecchio TeamResource li scriveva sulla collezione `teams`, che il modello
 * non ha mai registrato. Con l'arrivo di `logo` (Team::LOGO_CUSTOM) e
 * `logo-lvf` (Team::LOGO_IMPORTED) quei file sono rimasti in `media` ma
 * Team::logoUrl() non li legge più: non si vedono né nel CMS né sul sito.
 *
 * Qui si spostano su `logo`. Il file su disco non si tocca: il percorso
 * prodotto da DefaultPathGenerator dipende dall'id del media, non dalla
 * collezione, quindi basta rinominare `collection_name`.
 */
return new class extends Migration
{
    private const MODEL_TYPE = 'App\Models\Team';

    private const LEGACY_COLLECTION = 'teams';

    private const TARGET_COLLECTION = 'logo';

    public function up(): void
    {
        // Dal più recente: se una squadra ha più residui legacy si promuove
        // solo l'ultimo caricato, perché `logo` è singleFile.
        $legacy = DB::table('media')
            ->where('model_type', self::MODEL_TYPE)
            ->where('collection_name', self::LEGACY_COLLECTION)
            ->orderByDesc('id')
            ->get(['id', 'model_id', 'file_name', 'disk', 'custom_properties']);

        if ($legacy->isEmpty()) {
            return;
        }

        // Squadre che hanno già un logo caricato dal nuovo CMS: il loro media
        // legacy resta dov'è, il logo buono è quello che l'utente ha caricato.
        $occupate = DB::table('media')
            ->where('model_type', self::MODEL_TYPE)
            ->where('collection_name', self::TARGET_COLLECTION)
            ->pluck('model_id')
            ->flip()
            ->all();

        $promossi = [];
        $ignorati = [];

        foreach ($legacy as $media) {
            if (isset($occupate[$media->model_id])) {
                $ignorati[] = $media->id;

                continue;
            }

            if (! $this->fileEsiste($media)) {
                $ignorati[] = $media->id;

                continue;
            }

            $proprieta = json_decode($media->custom_properties ?: '[]', true);
            $proprieta = is_array($proprieta) ? $proprieta : [];
            // Marcatore di provenienza: serve a down() per rimettere indietro
            // esattamente queste righe e a nessun'altra.
            $proprieta['legacy_collection'] = self::LEGACY_COLLECTION;

            DB::table('media')
                ->where('id', $media->id)
                ->update([
                    'collection_name' => self::TARGET_COLLECTION,
                    'custom_properties' => json_encode($proprieta),
                    'updated_at' => now(),
                ]);

            $occupate[$media->model_id] = true;
            $promossi[] = $media->id;
        }

        Log::info('Migrazione loghi squadra legacy', [
            'promossi' => $promossi,
            'ignorati' => $ignorati,
        ]);
    }

    public function down(): void
    {
        $promossi = DB::table('media')
            ->where('model_type', self::MODEL_TYPE)
            ->where('collection_name', self::TARGET_COLLECTION)
            ->where('custom_properties->legacy_collection', self::LEGACY_COLLECTION)
            ->get(['id', 'custom_properties']);

        foreach ($promossi as $media) {
            $proprieta = json_decode($media->custom_properties ?: '[]', true);
            $proprieta = is_array($proprieta) ? $proprieta : [];
            unset($proprieta['legacy_collection']);

            DB::table('media')
                ->where('id', $media->id)
                ->update([
                    'collection_name' => self::LEGACY_COLLECTION,
                    'custom_properties' => json_encode($proprieta),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Un media legacy che ha perso il file su disco resta dov'è: promuoverlo
     * sostituirebbe un logo assente con un'immagine rotta.
     */
    private function fileEsiste(object $media): bool
    {
        $percorso = config('media-library.prefix', '').$media->id.'/'.$media->file_name;

        try {
            return Storage::disk($media->disk)->exists(ltrim($percorso, '/'));
        } catch (Throwable $e) {
            // Disco non raggiungibile durante il deploy: si promuove comunque,
            // il logo non si vede in ogni caso nello stato attuale.
            Log::warning('Verifica file logo legacy fallita', [
                'media_id' => $media->id,
                'errore' => $e->getMessage(),
            ]);

            return true;
        }
    }
};
