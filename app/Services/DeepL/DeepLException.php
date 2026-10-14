<?php

namespace App\Services\DeepL;

use RuntimeException;

/**
 * DeepL non raggiungibile o risposta non utilizzabile: la notizia resta con
 * l'inglese che aveva (o senza, e il sito mostra l'italiano).
 */
class DeepLException extends RuntimeException
{
    /**
     * Quota del mese esaurita (HTTP 456): riprovare fra un minuto non serve.
     */
    public function quotaEsaurita(): bool
    {
        return $this->getCode() === 456;
    }
}
