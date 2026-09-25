<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Mail\ConfermaIscrizioneNewsletter;
use App\Models\NewsletterSubscriber;
use App\Services\ActiveCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Log::spy();

        $this->post(route('newsletter.subscribe'), [
            'email' => 'riservato@example.com',
            'honeypot' => '',
            'privacy_accepted' => true,
        ]);

        Log::shouldHaveReceived('channel')->with('daily');
        Log::shouldNotHaveReceived('info', fn ($messaggio, $contesto = []) => str_contains(json_encode($contesto), 'riservato@example.com')
            || str_contains(json_encode($contesto), '127.0.0.1'));
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

        // Il record deve essere aggiornato
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'resubscribe@example.com',
            'first_name' => 'Nuovo',
            'unsubscribed_at' => null,
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
}
