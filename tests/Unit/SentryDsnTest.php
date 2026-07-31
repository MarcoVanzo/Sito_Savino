<?php

namespace Tests\Unit;

use App\Support\SentryDsn;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un DSN malformato non deve degradare la diagnostica: deve essere ignorato.
 *
 * Senza questo filtro il SDK lo rifiuta dentro `boot()` e fa fallire l'avvio
 * dell'intera applicazione — comprese le migrazioni in start.sh, quindi su App
 * Platform il container non parte affatto. È il modo peggiore in cui può
 * rompersi uno strumento di sorveglianza: al primo errore di battitura in una
 * variabile d'ambiente porta giù il sito che doveva sorvegliare.
 */
class SentryDsnTest extends TestCase
{
    #[Test]
    public function accetta_un_dsn_valido(): void
    {
        $dsn = 'https://esempio@o123.ingest.sentry.io/456';

        $this->assertSame($dsn, SentryDsn::sanitize($dsn));
    }

    #[Test]
    public function scarta_un_segnaposto_dimenticato_nella_spec(): void
    {
        // Il caso concreto: `.do/app.yaml` con un valore da sostituire a mano
        // e nessuno che lo sostituisce.
        $this->assertNull(SentryDsn::sanitize('DA_IMPOSTARE_NEL_PANNELLO_DO'));
    }

    #[Test]
    public function scarta_i_dsn_incompleti(): void
    {
        $this->assertNull(SentryDsn::sanitize('https://senza-progetto@sentry.io'));
        $this->assertNull(SentryDsn::sanitize('non-un-url'));
        $this->assertNull(SentryDsn::sanitize('ftp://chiave@sentry.io/1'));
    }

    #[Test]
    public function tratta_il_vuoto_come_assente(): void
    {
        $this->assertNull(SentryDsn::sanitize(''));
        $this->assertNull(SentryDsn::sanitize('   '));
        $this->assertNull(SentryDsn::sanitize(null));
    }

    #[Test]
    public function ripulisce_gli_spazi_accidentali(): void
    {
        // Un valore incollato nel pannello DO si porta dietro spazi con
        // facilità, e sarebbero sufficienti a far fallire il boot.
        $this->assertSame(
            'https://esempio@o123.ingest.sentry.io/456',
            SentryDsn::sanitize("  https://esempio@o123.ingest.sentry.io/456\n"),
        );
    }

    #[Test]
    public function ignora_i_tipi_non_stringa(): void
    {
        $this->assertNull(SentryDsn::sanitize(false));
        $this->assertNull(SentryDsn::sanitize(42));
        $this->assertNull(SentryDsn::sanitize([]));
    }
}
