<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

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
        ]);

        Queue::assertPushed(SyncNewsletterToActiveCampaign::class);
    }

    public function test_duplicate_email_returns_info_message(): void
    {
        Queue::fake();

        NewsletterSubscriber::create([
            'email' => 'existing@example.com',
            'source' => 'website',
            'subscribed_at' => now(),
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
            'synced_to_ac' => false, // deve risincronizzare
        ]);

        Queue::assertPushed(SyncNewsletterToActiveCampaign::class);
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
