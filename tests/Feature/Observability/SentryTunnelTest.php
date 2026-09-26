<?php

namespace Tests\Feature\Observability;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as RichiestaHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
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

    private function manda(string $busta, string $ip = '127.0.0.1')
    {
        return $this->call('POST', '/api/diagnostica', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'REMOTE_ADDR' => $ip], $busta);
    }

    #[Test]
    public function inoltra_la_busta_all_host_del_dsn(): void
    {
        $this->manda($this->busta())->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://o1.ingest.de.sentry.io/api/42/envelope/');
    }

    #[Test]
    public function inoltra_la_busta_intatta_senza_l_ip_del_visitatore(): void
    {
        // È ciò che l'informativa promette: Sentry riceve l'evento, non chi
        // lo ha generato. Il visitatore arriva con il suo IP e con un
        // X-Forwarded-For (come dietro il proxy di App Platform).
        $ip = '203.0.113.77';

        $this->call('POST', '/api/diagnostica', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'REMOTE_ADDR' => $ip,
            'HTTP_X_FORWARDED_FOR' => $ip,
            'HTTP_X_REAL_IP' => $ip,
        ], $this->busta())->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) use ($ip): bool {
            $intestazioni = json_encode($r->headers());

            return $r->body() === $this->busta()
                && ! str_contains($intestazioni, $ip)
                && ! str_contains($r->body(), $ip);
        });
    }

    #[Test]
    public function inoltra_solo_gli_eventi(): void
    {
        // Sessioni, replay e allegati non servono a trovare un guasto e
        // consumano la quota condivisa con gli errori del server.
        $intestazione = json_encode(['event_id' => 'abc', 'dsn' => self::DSN]);
        $evento = json_encode(['message' => 'boom']);
        $allegato = "riga uno\nriga due";
        $busta = $intestazione."\n"
            .json_encode(['type' => 'session'])."\n".json_encode(['sid' => 'x'])."\n"
            .json_encode(['type' => 'attachment', 'length' => strlen($allegato)])."\n".$allegato."\n"
            .json_encode(['type' => 'event'])."\n".$evento."\n"
            .json_encode(['type' => 'replay_recording', 'length' => 3])."\nabc";

        $this->manda($busta)->assertOk();

        Http::assertSent(fn (Request $r) => $r->body() === $intestazione."\n".json_encode(['type' => 'event'])."\n".$evento."\n");
    }

    #[Test]
    public function una_busta_senza_eventi_non_parte(): void
    {
        $busta = json_encode(['dsn' => self::DSN])."\n"
            .json_encode(['type' => 'session'])."\n".json_encode(['sid' => 'x']);

        $this->manda($busta)->assertStatus(202);

        Http::assertNothingSent();
    }

    #[Test]
    public function una_busta_malformata_si_rifiuta(): void
    {
        $this->manda(json_encode(['dsn' => self::DSN])."\nnon json\nx")->assertStatus(400);

        Http::assertNothingSent();
    }

    #[Test]
    public function un_indirizzo_oltre_il_suo_tetto_riceve_429(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->manda($this->busta(), '198.51.100.1')->assertOk();
        }

        $this->manda($this->busta(), '198.51.100.1')->assertStatus(429);
        // Un altro visitatore non paga per il primo.
        $this->manda($this->busta(), '198.51.100.2')->assertOk();
    }

    #[Test]
    public function il_tunnel_ha_anche_un_tetto_globale(): void
    {
        $limiti = RateLimiter::limiter('diagnostica')(RichiestaHttp::create('/api/diagnostica', 'POST', server: ['REMOTE_ADDR' => '198.51.100.9']));

        $this->assertSame([30, 300], array_map(fn ($l) => $l->maxAttempts, $limiti));
        $this->assertSame('diagnostica:globale', $limiti[1]->key);
        $this->assertStringContainsString('198.51.100.9', $limiti[0]->key);
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
