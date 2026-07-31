<?php

namespace Tests\Feature\Observability;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Sentry è l'unico canale che segnala un guasto in produzione: i log di App
 * Platform sono effimeri e nessuno li presidia. Questi test bloccano i tre modi
 * in cui la segnalazione può degradare senza che nessuno se ne accorga —
 * il client che parte in locale o nei test, i dati dei clienti spediti a un
 * terzo, e la quota bruciata dal rumore fino a nascondere gli errori veri.
 */
class SentryConfigurationTest extends TestCase
{
    #[Test]
    public function senza_dsn_il_client_resta_inerte(): void
    {
        // In locale e in CI il DSN è vuoto: nessuna connessione deve partire,
        // altrimenti ogni test che solleva un'eccezione tenterebbe una chiamata
        // di rete e la suite diventerebbe lenta e non deterministica.
        // `assertEmpty` e non `assertNull`: phpunit.xml lo forza a stringa vuota,
        // mentre senza quella riga sarebbe null. Entrambi rendono il client inerte.
        $this->assertEmpty(config('sentry.dsn'));
    }

    #[Test]
    public function i_dati_personali_non_vengono_spediti(): void
    {
        // Con send_default_pii attivo Sentry allega corpo della richiesta,
        // cookie e IP. Sul checkout significherebbe mandare indirizzi di
        // spedizione e dati dei clienti a un servizio terzo.
        $this->assertFalse(config('sentry.send_default_pii'));
    }

    #[Test]
    public function le_eccezioni_di_rumore_sono_escluse(): void
    {
        // Sono input sbagliati dell'utente, non difetti del codice: i 404 dei
        // crawler e i form compilati male seppellirebbero gli errori reali.
        $ignored = config('sentry.ignore_exceptions');

        $this->assertContains(NotFoundHttpException::class, $ignored);
        $this->assertContains(ValidationException::class, $ignored);
        $this->assertContains(AuthenticationException::class, $ignored);
        $this->assertContains(ThrottleRequestsException::class, $ignored);
    }

    #[Test]
    public function il_tracing_e_campionato(): void
    {
        // Campionare tutto esaurirebbe la quota mensile del piano gratuito in
        // pochi giorni, e a quota esaurita Sentry scarta anche gli errori.
        $rate = config('sentry.traces_sample_rate');

        $this->assertLessThanOrEqual(0.1, $rate);
        $this->assertGreaterThan(0, $rate);
    }

    #[Test]
    public function l_health_check_non_genera_transazioni(): void
    {
        // App Platform lo interroga di continuo: come transazione è solo
        // rumore che consuma quota.
        $this->assertContains('/up', config('sentry.ignore_transactions'));
    }
}
