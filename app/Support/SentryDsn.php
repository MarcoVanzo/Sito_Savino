<?php

namespace App\Support;

use Sentry\Dsn;
use Throwable;

/**
 * Normalizza il DSN di Sentry prima che il SDK lo veda.
 *
 * Senza questo filtro un DSN malformato non degrada: fa fallire il boot
 * dell'intera applicazione. Il ServiceProvider di Sentry costruisce le opzioni
 * del client dentro `boot()`, e un valore non interpretabile arriva a
 * `Symfony\OptionsResolver` come InvalidOptionsException — quindi non parte
 * nemmeno `php artisan migrate`, e su App Platform il container non si avvia
 * affatto.
 *
 * È il modo peggiore in cui può rompersi uno strumento di diagnostica: al
 * primo errore di battitura in una variabile d'ambiente porta giù il sito che
 * doveva sorvegliare. Meglio restare senza segnalazione degli errori — che è
 * la situazione da cui veniamo — che senza sito.
 */
class SentryDsn
{
    /**
     * @return string|null il DSN se utilizzabile, altrimenti null (client inerte)
     */
    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $dsn = trim($value);

        if ($dsn === '') {
            return null;
        }

        try {
            Dsn::createFromString($dsn);
        } catch (Throwable) {
            // Non si registra nulla e non si solleva: qui siamo nel caricamento
            // della configurazione, prima che esistano logger e gestore delle
            // eccezioni. L'assenza di eventi su Sentry è il segnale.
            return null;
        }

        return $dsn;
    }
}
