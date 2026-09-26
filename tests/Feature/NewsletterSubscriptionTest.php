<?php

namespace Tests\Feature;

use App\Http\Controllers\NewsletterController;
use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Mail\ConfermaIscrizioneNewsletter;
use App\Models\NewsletterSubscriber;
use App\Services\ActiveCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * Doppio opt-in: la richiesta registra l'indirizzo e manda il link di
     * conferma, ma ad ActiveCampaign non arriva niente finché il proprietario
     * della casella non clicca.
     */
    public function test_user_can_subscribe_to_newsletter(): void
    {
        Queue::fake();

        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'tifoso@example.com',
            'first_name' => 'Marco',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'tifoso@example.com',
            'first_name' => 'Marco',
            'source' => 'website',
            'confermato_il' => null,
        ]);

        Mail::assertQueued(ConfermaIscrizioneNewsletter::class, fn ($mail) => $mail->hasTo('tifoso@example.com'));
        Queue::assertNotPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_la_conferma_dal_link_attiva_l_iscrizione_e_la_manda_ad_activecampaign(): void
    {
        Queue::fake();

        $iscritto = NewsletterSubscriber::factory()->nonConfermato()->create();

        $pagina = URL::temporarySignedRoute('newsletter.conferma.show', now()->addDay(), ['subscriber' => $iscritto->id]);

        // Aprire il link non conferma: lo fanno anche i filtri della posta.
        $this->get($pagina)->assertOk();
        $this->assertNull($iscritto->fresh()->confermato_il);
        Queue::assertNotPushed(SyncNewsletterToActiveCampaign::class);

        $conferma = URL::temporarySignedRoute('newsletter.conferma', now()->addHour(), ['subscriber' => $iscritto->id]);
        $this->post($conferma)->assertRedirect();

        $this->assertNotNull($iscritto->fresh()->confermato_il);
        Queue::assertPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_il_link_di_conferma_scade(): void
    {
        $iscritto = NewsletterSubscriber::factory()->nonConfermato()->create();

        $link = URL::temporarySignedRoute('newsletter.conferma.show', now()->subMinute(), ['subscriber' => $iscritto->id]);

        $this->get($link)->assertForbidden();
    }

    public function test_un_iscritto_non_confermato_non_arriva_ad_activecampaign(): void
    {
        $iscritto = NewsletterSubscriber::factory()->nonConfermato()->create();

        $servizio = $this->mock(ActiveCampaignService::class);
        $servizio->shouldReceive('isConfigured')->andReturn(true);
        $servizio->shouldNotReceive('syncContact');

        (new SyncNewsletterToActiveCampaign($iscritto))->handle($servizio);

        $this->assertFalse($iscritto->fresh()->synced_to_ac);
    }

    public function test_nel_log_non_finiscono_email_e_ip(): void
    {
        // Un canale vero che scrive in memoria: con Log::spy() la chiamata a
        // channel() restituiva null, il controller andava in errore e
        // l'asserzione sull'assenza era vera per forza.
        $righe = [];
        Log::shouldReceive('channel')->with('daily')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function ($messaggio, $contesto = []) use (&$righe) {
            $righe[] = $messaggio.' '.json_encode($contesto);
        });

        $this->post(route('newsletter.subscribe'), [
            'email' => 'riservato@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertNotEmpty($righe, 'L\'iscrizione deve lasciare una riga nel log');
        foreach ($righe as $riga) {
            $this->assertStringNotContainsString('riservato@example.com', $riga);
            $this->assertStringNotContainsString('127.0.0.1', $riga);
        }
    }

    public function test_una_richiesta_mai_confermata_si_cancella_dopo_trenta_giorni(): void
    {
        $vecchia = NewsletterSubscriber::factory()->nonConfermato()->create(['subscribed_at' => now()->subDays(31)]);
        $recente = NewsletterSubscriber::factory()->nonConfermato()->create(['subscribed_at' => now()->subDays(5)]);
        $confermata = NewsletterSubscriber::factory()->create(['subscribed_at' => now()->subDays(90), 'confermato_il' => now()->subDays(89)]);

        $this->artisan('model:prune', ['--model' => [NewsletterSubscriber::class]])->assertSuccessful();

        $this->assertModelMissing($vecchia);
        $this->assertModelExists($recente);
        $this->assertModelExists($confermata);
    }

    public function test_la_richiesta_di_un_disiscritto_non_cancella_la_disiscrizione_finche_non_conferma(): void
    {
        Queue::fake();

        $uscito = NewsletterSubscriber::factory()->create([
            'email' => 'uscito@example.com',
            'confermato_il' => now()->subMonths(3),
            'unsubscribed_at' => now()->subMonth(),
        ]);

        $this->post(route('newsletter.subscribe'), [
            'email' => 'uscito@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $this->assertNotNull($uscito->fresh()->unsubscribed_at);

        $this->assertTrue($uscito->fresh()->conferma());
        $this->assertNull($uscito->fresh()->unsubscribed_at);
    }

    public function test_duplicate_email_returns_info_message(): void
    {
        Queue::fake();

        NewsletterSubscriber::create([
            'email' => 'existing@example.com',
            'source' => 'website',
            'subscribed_at' => now(),
            'confermato_il' => now(),
            'synced_to_ac' => true,
        ]);

        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'existing@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('newsletter_info');

        $this->assertDatabaseCount('newsletter_subscribers', 1);

        Queue::assertNotPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_honeypot_filled_silently_rejects(): void
    {
        Queue::fake();

        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'bot@spam.com',
            'honeypot' => 'I am a bot',
            'privacy_accepted' => true,
        ]);

        // La validazione max:0 sul honeypot blocca la request prima del controller
        $response->assertSessionHasErrors('honeypot');

        // Should NOT create a record
        $this->assertDatabaseMissing('newsletter_subscribers', [
            'email' => 'bot@spam.com',
        ]);

        Queue::assertNotPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_invalid_email_returns_validation_error(): void
    {
        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'not-an-email',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_missing_privacy_consent_returns_error(): void
    {
        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'test@example.com',
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors('privacy_accepted');
    }

    public function test_previously_unsubscribed_user_can_resubscribe(): void
    {
        Queue::fake();

        // Crea un iscritto che si è disiscritto
        NewsletterSubscriber::create([
            'email' => 'resubscribe@example.com',
            'first_name' => 'Vecchio',
            'source' => 'website',
            'subscribed_at' => now()->subMonth(),
            'unsubscribed_at' => now()->subWeek(),
            'synced_to_ac' => true,
            'ac_contact_id' => 999,
        ]);

        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'resubscribe@example.com',
            'first_name' => 'Nuovo',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Deve riattivare, non creare un duplicato
        $this->assertDatabaseCount('newsletter_subscribers', 1);

        // Il record deve essere aggiornato; la disiscrizione resta finché il
        // titolare non conferma dal link.
        $this->assertNotNull(NewsletterSubscriber::sole()->unsubscribed_at);
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'resubscribe@example.com',
            'first_name' => 'Nuovo',
            'confermato_il' => null, // la conferma si richiede
            'synced_to_ac' => false, // deve risincronizzare, dopo la conferma
        ]);

        Mail::assertQueued(ConfermaIscrizioneNewsletter::class);
        Queue::assertNotPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        Queue::fake();

        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'Tifoso@Example.COM',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'tifoso@example.com',
        ]);
    }

    public function test_resubscribe_keeps_ac_contact_id(): void
    {
        Queue::fake();

        NewsletterSubscriber::create([
            'email' => 'keep@example.com',
            'source' => 'website',
            'subscribed_at' => now()->subMonth(),
            'unsubscribed_at' => now()->subWeek(),
            'synced_to_ac' => true,
            'ac_contact_id' => 12345,
        ]);

        $this->post(route('newsletter.subscribe'), [
            'email' => 'keep@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'keep@example.com',
            'ac_contact_id' => 12345,
            'synced_to_ac' => false,
        ]);
    }

    public function test_active_but_unsynced_user_resubscribing_triggers_sync_job(): void
    {
        Queue::fake();

        NewsletterSubscriber::create([
            'email' => 'unsynced@example.com',
            'source' => 'website',
            'subscribed_at' => now(),
            'confermato_il' => now(),
            'synced_to_ac' => false,
        ]);

        $response = $this->post(route('newsletter.subscribe'), [
            'email' => 'unsynced@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        $response->assertSessionHas('newsletter_info');
        Queue::assertPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_chi_si_disiscrive_prima_che_il_job_giri_non_arriva_ad_activecampaign(): void
    {
        // Il job porta con sé l'istanza di quando è stato accodato: la
        // disiscrizione arrivata nel frattempo va riletta dal database.
        $iscritto = NewsletterSubscriber::factory()->create(['confermato_il' => now(), 'unsubscribed_at' => null, 'synced_to_ac' => false]);
        $job = new SyncNewsletterToActiveCampaign($iscritto);

        NewsletterSubscriber::whereKey($iscritto->id)->update(['unsubscribed_at' => now()]);

        $servizio = $this->mock(ActiveCampaignService::class);
        $servizio->shouldReceive('isConfigured')->andReturn(true);
        $servizio->shouldNotReceive('syncContact');

        $job->handle($servizio);

        $this->assertFalse($iscritto->fresh()->synced_to_ac);
    }

    public function test_un_iscritto_cancellato_prima_che_il_job_giri_non_arriva_ad_activecampaign(): void
    {
        $iscritto = NewsletterSubscriber::factory()->create(['confermato_il' => now()]);
        $job = new SyncNewsletterToActiveCampaign($iscritto);
        NewsletterSubscriber::whereKey($iscritto->id)->delete();

        $servizio = $this->mock(ActiveCampaignService::class);
        $servizio->shouldReceive('isConfigured')->andReturn(true);
        $servizio->shouldNotReceive('syncContact');

        $job->handle($servizio);
        $this->assertTrue(true);
    }

    public function test_le_email_di_conferma_allo_stesso_indirizzo_hanno_un_tetto_giornaliero(): void
    {
        // Il limite della rotta conta per IP: senza un tetto per indirizzo,
        // una casella altrui si riempie di conferme cambiando rete.
        Queue::fake();
        $this->withoutMiddleware(ThrottleRequests::class);

        for ($i = 0; $i < NewsletterController::CONFERME_AL_GIORNO + 2; $i++) {
            $this->post(route('newsletter.subscribe'), [
                'email' => 'Bersaglio@example.com',
                'honeypot' => '',
                'privacy_accepted' => true,
            ])->assertRedirect()->assertSessionHas('success');
        }

        Mail::assertQueuedCount(NewsletterController::CONFERME_AL_GIORNO);

        // Un altro indirizzo ha il suo contatore.
        $this->post(route('newsletter.subscribe'), [
            'email' => 'altro@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);
        Mail::assertQueuedCount(NewsletterController::CONFERME_AL_GIORNO + 1);

        // Domani si riparte.
        $this->travel(25)->hours();
        $this->post(route('newsletter.subscribe'), [
            'email' => 'bersaglio@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);
        Mail::assertQueuedCount(NewsletterController::CONFERME_AL_GIORNO + 2);
    }
}
