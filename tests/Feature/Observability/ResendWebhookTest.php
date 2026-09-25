<?php

namespace Tests\Feature\Observability;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le email che Resend non ha consegnato diventano un avviso: il cliente che ha
 * pagato e non ha ricevuto la conferma va contattato per altra via.
 */
class ResendWebhookTest extends TestCase
{
    private const SEGRETO = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.resend.webhook_secret' => self::SEGRETO,
            'services.avvisi.email' => 'allarmi@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $evento
     */
    private function notifica(array $evento, ?int $timestamp = null, ?string $segreto = null)
    {
        $corpo = json_encode($evento);
        $id = 'msg_'.uniqid();
        $timestamp ??= now()->getTimestamp();
        $chiave = base64_decode(substr($segreto ?? self::SEGRETO, 6));
        $firma = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$corpo}", $chiave, true));

        return $this->call('POST', '/api/webhooks/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => "v1,firma-vecchia v1,{$firma}",
        ], $corpo);
    }

    /**
     * @return array<string, mixed>
     */
    private function rimbalzo(string $a = 'cliente@example.com', string $tipo = 'email.bounced'): array
    {
        return [
            'type' => $tipo,
            'data' => [
                'email_id' => 'e-1',
                'to' => [$a],
                'subject' => 'Conferma ordine #1234',
                'bounce' => ['message' => 'Mailbox does not exist'],
            ],
        ];
    }

    /** @return list<string> */
    private function oggetti(): array
    {
        return AvvisoTecnicoTest::inviate()->map(fn ($m) => $m->getOriginalMessage()->getSubject())->values()->all();
    }

    #[Test]
    public function un_rimbalzo_diventa_un_avviso(): void
    {
        $this->notifica($this->rimbalzo())->assertNoContent();

        $this->assertSame(['[Sito Savino] Email rimbalzata: cliente@example.com'], $this->oggetti());
        $this->assertStringContainsString(
            'Mailbox does not exist',
            AvvisoTecnicoTest::inviate()->sole()->getOriginalMessage()->getTextBody(),
        );
    }

    #[Test]
    public function la_stessa_email_non_avvisa_due_volte(): void
    {
        $this->notifica($this->rimbalzo());
        $this->notifica($this->rimbalzo());

        $this->assertCount(1, $this->oggetti());
    }

    #[Test]
    public function gli_eventi_di_consegna_riuscita_si_ignorano(): void
    {
        $this->notifica($this->rimbalzo(tipo: 'email.delivered'))->assertNoContent();

        $this->assertSame([], $this->oggetti());
    }

    #[Test]
    public function una_firma_sbagliata_o_vecchia_si_rifiuta(): void
    {
        $this->notifica($this->rimbalzo(), segreto: 'whsec_'.base64_encode('altro-segreto'))->assertStatus(400);
        // Una notifica catturata e rigiocata dopo dieci minuti.
        $this->notifica($this->rimbalzo(), timestamp: now()->subMinutes(10)->getTimestamp())->assertStatus(400);

        $this->assertSame([], $this->oggetti());
    }

    #[Test]
    public function senza_segreto_rifiuta_tutto(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $this->notifica($this->rimbalzo())->assertStatus(400);
    }

    #[Test]
    public function un_allarme_che_rimbalza_non_genera_un_altro_allarme(): void
    {
        $this->notifica($this->rimbalzo('Allarmi@example.com'))->assertNoContent();

        $this->assertSame([], $this->oggetti());
    }
}
