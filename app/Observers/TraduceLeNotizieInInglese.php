<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Jobs\TraduciLaNotiziaInInglese;
use App\Models\Post;
use App\Services\DeepL\DeepLClient;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Una notizia pubblicata a cui cambia l'italiano (o che viene pubblicata
 * adesso) va in coda per la traduzione in inglese con DeepL. Le modifiche
 * al solo inglese, compreso il salvataggio del job stesso, non accodano
 * niente. Senza chiave DeepL non parte niente.
 */
class TraduceLeNotizieInInglese implements ShouldHandleEventsAfterCommit
{
    public function created(Post $post): void
    {
        $this->accodaSePubblicata($post);
    }

    public function updated(Post $post): void
    {
        if ($post->wasChanged('status') || $this->italianoCambiato($post)) {
            $this->accodaSePubblicata($post);
        }
    }

    private function accodaSePubblicata(Post $post): void
    {
        if ($post->status !== PostStatus::Published || ! DeepLClient::fromConfig()->configurato()) {
            return;
        }

        TraduciLaNotiziaInInglese::accoda($post->id);
    }

    /**
     * Dopo il commit l'originale è già quello nuovo: i valori di prima stanno
     * in `getPrevious()`, come JSON per lingua.
     */
    private function italianoCambiato(Post $post): bool
    {
        $prima = $post->getPrevious();

        foreach ($post->getTranslatableAttributes() as $campo) {
            if (! $post->wasChanged($campo)) {
                continue;
            }

            $vecchio = json_decode((string) ($prima[$campo] ?? ''), true);
            $italianoDiPrima = is_array($vecchio) ? (string) ($vecchio['it'] ?? '') : '';

            if ($italianoDiPrima !== (string) $post->getTranslation($campo, 'it', false)) {
                return true;
            }
        }

        return false;
    }
}
