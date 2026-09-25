<?php

namespace Tests\Feature\Observability;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il tunnel verso Sentry degli errori JavaScript. Non deve diventare un
 * inoltro aperto: solo buste per il DSN del sito, solo verso il suo host.
 */
class SentryTunnelTest extends TestCase
{
    private const DSN = 'https://chiave@o1.ingest.de.sentry.io/42';

    protected function setUp(): void
    {
        parent::setUp();

        config(['sentry.dsn' => self::DSN]);
        Http::fake(['*' => fn () => $this->rispostaDiSentry ?? Http::response('{}', 200)]);
    }

    /** Quello che risponde Sentry, se non il 200 di serie. */
    private $rispostaDiSentry = null;

    private function busta(string $dsn = self::DSN): string
    {
        return json_encode(['event_id' => 'abc', 'dsn' => $dsn])."\n"
            .json_encode(['type' => 'event'])."\n"
            .json_encode(['message' => 'boom']);
    }

    private function manda(string $busta)
    {
        return $this->call('POST', '/api/diagnostica', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $busta);
    }

    #[Test]
    public function inoltra_la_busta_all_host_del_dsn(): void
    {
        $this->manda($this->busta())->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://o1.ingest.de.sentry.io/api/42/envelope/'
            && $r->body() === $this->busta()
            // Il server non aggiunge l'IP del visitatore: nessuna intestazione
            // di inoltro nella richiesta verso Sentry.
            && ! $r->hasHeader('X-Forwarded-For'));
    }

    #[Test]
    public function rifiuta_buste_per_un_altro_progetto(): void
    {
        $this->manda($this->busta('https://altra@evil.example/1'))->assertStatus(400);

        Http::assertNothingSent();
    }

    #[Test]
    public function rifiuta_corpi_vuoti_o_troppo_grandi(): void
    {
        $this->manda('')->assertStatus(400);
        $this->manda($this->busta().str_repeat('x', 250 * 1024))->assertStatus(400);
        $this->call('POST', '/api/diagnostica', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'CONTENT_LENGTH' => (string) (250 * 1024)], $this->busta())
            ->assertStatus(413);

        Http::assertNothingSent();
    }

    #[Test]
    public function il_limite_di_sentry_torna_al_browser(): void
    {
        // Senza il 429 l'SDK non rallenta e un browser in un ciclo d'errore
        // consuma la quota condivisa con gli errori del server.
        $this->rispostaDiSentry = Http::response('', 429, ['X-Sentry-Rate-Limits' => '60:error:key', 'Retry-After' => '60']);

        $this->manda($this->busta())
            ->assertStatus(429)
            ->assertHeader('X-Sentry-Rate-Limits', '60:error:key')
            ->assertHeader('Retry-After', '60');
    }

    #[Test]
    public function senza_dsn_non_inoltra_niente(): void
    {
        config(['sentry.dsn' => null]);

        $this->manda($this->busta())->assertStatus(400);

        Http::assertNothingSent();
    }

    #[Test]
    public function il_dsn_arriva_alle_pagine(): void
    {
        $this->get('/')->assertInertia(fn ($pagina) => $pagina->where('diagnostica.dsn', self::DSN));
    }
}
