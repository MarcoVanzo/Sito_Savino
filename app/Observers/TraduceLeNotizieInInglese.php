<?php

namespace App\Observers;

use App\Jobs\TraduciLaNotiziaInInglese;
use App\Models\Post;

/**
 * Ogni salvataggio di una notizia con l'italiano cambiato accoda la
 * traduzione inglese, che parte mezz'ora dopo: se la redazione salva ancora,
 * vale solo l'ultima.
 */
class TraduceLeNotizieInInglese
{
    public function saved(Post $post): void
    {
        TraduciLaNotiziaInInglese::richiediPer($post);
    }
}
