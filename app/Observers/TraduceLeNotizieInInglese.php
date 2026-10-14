<?php

namespace App\Observers;

use App\Jobs\TraduciLaNotiziaInInglese;
use App\Models\Post;
use App\Services\DeepL\DeepLClient;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Una notizia creata o modificata va in coda per la traduzione in inglese
 * (DeepL). Il job decide campo per campo cosa tradurre e non tocca l'inglese
 * scritto dalla redazione. Senza chiave DeepL non parte niente.
 */
class TraduceLeNotizieInInglese implements ShouldHandleEventsAfterCommit
{
    public function saved(Post $post): void
    {
        if (! $post->wasRecentlyCreated && ! $post->wasChanged($post->getTranslatableAttributes())) {
            return;
        }

        if (! DeepLClient::fromConfig()->configurato()) {
            return;
        }

        TraduciLaNotiziaInInglese::dispatch($post->id);
    }
}
