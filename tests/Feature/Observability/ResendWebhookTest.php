<?php

namespace Tests\Feature\Observability;

use App\Http\Controllers\Webhooks\ResendWebhookController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le email che Resend non ha consegnato diventano un avviso: il cliente che ha
 * pagato e non ha ricevuto la conferma va contattato per altra via.
 */
class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SEGRETO = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.resend.webhook_secret' => self::SEGRETO,
            'services.avvisi.email' => 'allarmi@example.com',
        ]);

        $this->ordineDi('cliente@example.com');
    }

    private function ordineDi(string $email, int $giorniFa = 1): Order
    {
        $ordine = Order::factory()->create(['user_id' => null, 'guest_email' => $email]);
        $ordine->forceFill(['created_at' => now()->subDays($giorniFa)])->save();

        return $ordine;
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
    public function vale_anche_per_l_email_dell_account(): void
    {
        $utente = User::factory()->create(['email' => 'socio@example.com']);
        Order::factory()->create(['user_id' => $utente->id]);

        $this->notifica($this->rimbalzo('Socio@example.com'))->assertNoContent();

        $this->assertSame(['[Sito Savino] Email rimbalzata: Socio@example.com'], $this->oggetti());
    }

    #[Test]
    public function un_indirizzo_senza_ordini_recenti_resta_nel_log_senza_email(): void
    {
        // Newsletter e ricevute del recesso vanno a indirizzi scritti da
        // chiunque: cento indirizzi inesistenti non devono diventare cento
        // email ad allarmi@. Nel log nemmeno l'indirizzo.
        $this->ordineDi('vecchio@example.com', giorniFa: 45);
        Log::spy();

        $this->notifica($this->rimbalzo('iscritto@example.com'))->assertNoContent();
        $this->notifica($this->rimbalzo('vecchio@example.com'))->assertNoContent();

        $this->assertSame([], $this->oggetti());
        Log::shouldHaveReceived('warning')->twice()->withArgs(
            fn (string $messaggio, array $contesto) => ! str_contains($messaggio.json_encode($contesto), '@example.com'),
        );
    }

    #[Test]
    public function gli_avvisi_hanno_un_tetto_orario(): void
    {
        for ($i = 0; $i < ResendWebhookController::AVVISI_ALL_ORA + 3; $i++) {
            $this->ordineDi("cliente{$i}@example.com");
            $this->notifica($this->rimbalzo("cliente{$i}@example.com"))->assertNoContent();
        }

        $this->assertCount(ResendWebhookController::AVVISI_ALL_ORA, $this->oggetti());

        // Passata l'ora si torna ad avvisare.
        $this->travel(61)->minutes();
        $this->ordineDi('dopo@example.com');
        $this->notifica($this->rimbalzo('dopo@example.com'));

        $this->assertCount(ResendWebhookController::AVVISI_ALL_ORA + 1, $this->oggetti());
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
