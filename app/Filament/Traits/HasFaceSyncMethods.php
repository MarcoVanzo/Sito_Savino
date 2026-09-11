<?php

namespace App\Filament\Traits;

use App\Models\Roster;
use App\Services\FacialRecognitionService;
use Illuminate\Http\Client\ConnectionException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasFaceSyncMethods
{
    private const SERVIZIO_AI_IRRAGGIUNGIBILE = 'Servizio AI non raggiungibile: riprova più tardi.';

    /**
     * Restituisce la lista dei media da sincronizzare per un roster.
     */
    public function getMediaForSync(int $rosterId): array
    {
        $roster = Roster::with(['player', 'media'])->find($rosterId);

        if (! $roster) {
            return [];
        }

        $allMedia = collect();

        $officialPhoto = $roster->getFirstMedia('rosters_official');
        if ($officialPhoto) {
            $allMedia->push($officialPhoto);
        }

        // RIMOSSO: Le foto in azione inquinano l'addestramento (ci sono avversarie, altre giocatrici in primo piano, ecc.)
        // $actionPhotos = $roster->getMedia('rosters_action');
        // if ($actionPhotos->isNotEmpty()) {
        //     $allMedia = $allMedia->merge($actionPhotos);
        // }

        // Fallback: avatar del player
        if ($allMedia->isEmpty()) {
            $playerAvatar = $roster->player->getFirstMedia('players');
            if ($playerAvatar) {
                $allMedia->push($playerAvatar);
            }
        }

        return $allMedia->map(fn (Media $m) => [
            'id' => $m->id,
            'name' => $m->file_name,
            'url' => $m->getUrl($m->hasGeneratedConversion('thumb') ? 'thumb' : ''),
            'type' => $m->collection_name === 'rosters_official' ? 'Ufficiale' : 'Azione',
        ])->values()->toArray();
    }

    /**
     * Sincronizza una singola foto con CompreFace.
     */
    public function syncSinglePhoto(int $rosterId, int $mediaId): array
    {
        $roster = Roster::with('player')->find($rosterId);
        $media = Media::find($mediaId);

        if (! $roster || ! $media) {
            return ['success' => false, 'error' => 'Record non trovato'];
        }

        $service = app(FacialRecognitionService::class);

        try {
            $result = $service->addFaceExampleFromMedia($roster->player, $media);
        } catch (ConnectionException $e) {
            // Il riquadro di avanzamento mostra l'errore foto per foto: un
            // server irraggiungibile non deve diventare un 500 del pannello.
            report($e);

            return [
                'success' => false,
                'error' => self::SERVIZIO_AI_IRRAGGIUNGIBILE,
                'newScore' => $roster->player->ai_face_examples,
            ];
        }

        $success = is_array($result) ? $result['success'] : (bool) $result;
        $error = is_array($result) ? ($result['error'] ?? null) : null;

        // Salva lo stato di sync sulla custom property del media
        $media->setCustomProperty('ai_synced', $success);
        $media->setCustomProperty('ai_synced_at', now()->toIso8601String());
        if (! $success && $error) {
            $media->setCustomProperty('ai_sync_error', $error);
        } else {
            $media->forgetCustomProperty('ai_sync_error');
        }
        $media->save();

        if ($success) {
            $roster->player->increment('ai_face_examples');
        }

        return [
            'success' => $success,
            'error' => $error,
            'newScore' => $roster->player->fresh()->ai_face_examples,
        ];
    }

    /**
     * Prepara la sincronizzazione azzerando gli esempi su CompreFace e nel database.
     */
    public function prepareForSync(int $rosterId): void
    {
        $roster = Roster::with('player')->find($rosterId);
        if ($roster && $roster->player) {
            $service = app(FacialRecognitionService::class);

            try {
                $cancellati = $service->deleteAllSubjectExamples($roster->player);
            } catch (ConnectionException $e) {
                // Le foto successive falliranno una per una con il messaggio
                // leggibile: qui basta non rompere la richiesta.
                report($e);

                return;
            }

            // Il contatore si azzera solo se gli esempi sono spariti davvero
            // da CompreFace, altrimenti il pannello direbbe il falso.
            if ($cancellati) {
                $roster->player->update(['ai_face_examples' => 0]);
            }
        }
    }
}
