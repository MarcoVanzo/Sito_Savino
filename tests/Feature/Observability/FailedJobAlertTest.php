<?php

namespace Tests\Feature\Observability;

use App\Enums\UserRole;
use App\Listeners\AlertOnFailedJob;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * I job in coda spediscono le conferme d'ordine e le email ai vincitori d'asta.
 * Quando falliscono finiscono in `failed_jobs`, una tabella che nessuno apre:
 * il guasto si manifestava come un cliente che non riceve nulla e scrive per
 * chiedere perché.
 */
class FailedJobAlertTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Il ruolo si assegna con forceFill dopo la creazione: il factory non lo
     * accetta fra gli attributi, come già fanno gli altri test del progetto.
     */
    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin->value])->save();

        return $user;
    }

    private function fireFailure(string $jobName = 'App\\Jobs\\SendOrderConfirmation', string $queue = 'default'): void
    {
        $job = $this->createMock(Job::class);
        $job->method('resolveName')->willReturn($jobName);
        $job->method('getQueue')->willReturn($queue);

        app(AlertOnFailedJob::class)->handle(
            new JobFailed('database', $job, new RuntimeException('SMTP irraggiungibile')),
        );
    }

    #[Test]
    public function avvisa_i_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->fireFailure();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($admin->id, DB::table('notifications')->value('notifiable_id'));
    }

    #[Test]
    public function fallimenti_ripetuti_dello_stesso_job_non_inondano_il_pannello(): void
    {
        $this->superAdmin();

        // Un gateway di posta irraggiungibile produce decine di fallimenti
        // identici in pochi minuti: altrettante notifiche renderebbero il
        // pannello inutilizzabile proprio mentre serve.
        $this->fireFailure();
        $this->fireFailure();
        $this->fireFailure();

        $this->assertDatabaseCount('notifications', 1);
    }

    #[Test]
    public function job_diversi_avvisano_separatamente(): void
    {
        $this->superAdmin();

        // Il silenziatore è per tipo di job: un secondo guasto, diverso dal
        // primo, non deve restare nascosto dalla finestra del primo.
        $this->fireFailure('App\\Jobs\\SendOrderConfirmation');
        $this->fireFailure('App\\Jobs\\NotifyAuctionWinner');

        $this->assertDatabaseCount('notifications', 2);
    }

    #[Test]
    public function scaduta_la_finestra_si_torna_ad_avvisare(): void
    {
        $this->superAdmin();

        $this->fireFailure();
        $this->travel(61)->minutes();
        $this->fireFailure();

        $this->assertDatabaseCount('notifications', 2);
    }

    #[Test]
    public function un_email_non_partita_non_silenzia_il_fallimento_successivo(): void
    {
        config(['services.avvisi.email' => 'marco@example.com']);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Resend irraggiungibile'));
        Mail::shouldReceive('raw')->once();

        $this->fireFailure();
        $this->fireFailure();
        $this->fireFailure();
    }

    #[Test]
    public function avvisa_anche_per_email(): void
    {
        config(['services.avvisi.email' => 'marco@example.com']);

        $this->fireFailure();

        $this->assertSame(
            '[Sito Savino] Job in coda fallito: SendOrderConfirmation',
            AvvisoTecnicoTest::inviate()->sole()->getOriginalMessage()->getSubject(),
        );
    }

    #[Test]
    public function la_coda_ai_resta_nel_pannello(): void
    {
        // L'analisi dei volti dipende da CompreFace e si recupera da sola al
        // giro orario: un servizio lento non deve riempire la casella.
        config(['services.avvisi.email' => 'marco@example.com']);
        $this->superAdmin();

        $this->fireFailure('App\\Jobs\\AnalyzeGalleryImageJob', 'ai');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertCount(0, AvvisoTecnicoTest::inviate());
    }
}
