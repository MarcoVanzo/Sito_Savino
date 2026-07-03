<?php

namespace App\Filament\Traits;

use App\Models\Roster;
use App\Services\FacialRecognitionService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasFaceSyncMethods
{
    /**
     * Restituisce la lista dei media da sincronizzare per un roster.
     */
    public function getMediaForSync(int $rosterId): array
    {
        $roster = Roster::with(['player', 'media'])->find($rosterId);

        if (! $roster) {
            return [];
        }

        // Reset contatore AI prima di ri-addestrare (evita counter drift)
        $roster->player->update(['ai_face_examples' => 0]);

        $allMedia = collect();

        $officialPhoto = $roster->getFirstMedia('rosters_official');
        if ($officialPhoto) {
            $allMedia->push($officialPhoto);
        }

        $actionPhotos = $roster->getMedia('rosters_action');
        if ($actionPhotos->isNotEmpty()) {
            $allMedia = $allMedia->merge($actionPhotos);
        }

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
        $result = $service->addFaceExampleFromMedia($roster->player, $media);

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
}
