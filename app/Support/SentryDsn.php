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

    /**
     * Il DSN che usa il browser (resources/js/diagnostica.js).
     *
     * Ha un progetto Sentry suo (`SENTRY_BROWSER_DSN`) perché la quota di eventi
     * è per progetto: un errore JavaScript ripetuto su migliaia di visite la
     * consumerebbe tutta, e da lì in poi gli errori del server — pagamenti,
     * webhook, coda — non arriverebbero più. Senza la variabile si ripiega sul
     * DSN del server, come prima.
     */
    public static function perIlBrowser(): ?string
    {
        return config('services.sentry.browser_dsn') ?: (config('sentry.dsn') ?: null);
    }

    /**
     * I DSN per cui il tunnel accetta una busta: quello del browser e quello
     * del server, che resta valido per le pagine ancora in cache di un minuto
     * prima (CachePublicResponse) quando si cambia progetto.
     *
     * @return list<string>
     */
    public static function ammessiDalTunnel(): array
    {
        return array_values(array_unique(array_filter([
            self::perIlBrowser(),
            config('sentry.dsn') ?: null,
        ])));
    }
}
