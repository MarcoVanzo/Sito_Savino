<?php

namespace Tests\Feature;

use App\Jobs\UnsubscribeNewsletterFromActiveCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewsletterUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::create(array_merge([
            'email' => 'tifoso@example.com',
            'source' => 'website',
            'subscribed_at' => now(),
            'synced_to_ac' => true,
            'ac_contact_id' => 4242,
        ], $attributes));
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $subscriber = $this->subscriber();

        $this->get(route('newsletter.unsubscribe.show', ['subscriber' => $subscriber->id]))
            ->assertForbidden();
    }

    public function test_tampered_link_does_not_unsubscribe_someone_else(): void
    {
        $mine = $this->subscriber();
        $other = $this->subscriber(['email' => 'altro@example.com']);

        // Firma valida per un iscritto, id sostituito con quello di un altro.
        $tampered = str_replace(
            '/'.$mine->id.'?',
            '/'.$other->id.'?',
            $mine->unsubscribeUrl()
        );

        $this->get($tampered)->assertForbidden();

        $this->assertTrue($other->fresh()->isSubscribed());
    }

    public function test_signed_get_shows_the_confirmation_without_unsubscribing(): void
    {
        Queue::fake();

        $subscriber = $this->subscriber();

        $this->get($subscriber->unsubscribeUrl())->assertOk();

        $this->assertTrue(
            $subscriber->fresh()->isSubscribed(),
            'Il solo caricamento della pagina non deve disiscrivere: i client di posta seguono i link in prefetch.'
        );
        Queue::assertNotPushed(UnsubscribeNewsletterFromActiveCampaign::class);
    }

    public function test_confirmation_unsubscribes_and_propagates_to_activecampaign(): void
    {
        Queue::fake();

        $subscriber = $this->subscriber();

        $this->post($subscriber->unsubscribeUrl())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);

        Queue::assertPushed(
            UnsubscribeNewsletterFromActiveCampaign::class,
            fn (UnsubscribeNewsletterFromActiveCampaign $job) => $job->contactId === 4242 && ! $job->deleteContact
        );
    }

    public function test_unsubscribing_twice_keeps_the_first_date(): void
    {
        Queue::fake();

        $subscriber = $this->subscriber(['unsubscribed_at' => now()->subMonth()]);
        $firstDate = $subscriber->unsubscribed_at;

        $this->post($subscriber->unsubscribeUrl())->assertRedirect();

        $this->assertTrue($firstDate->equalTo($subscriber->fresh()->unsubscribed_at));
        Queue::assertNotPushed(UnsubscribeNewsletterFromActiveCampaign::class);
    }

    public function test_deleting_a_subscriber_removes_the_contact_from_activecampaign(): void
    {
        Queue::fake();

        $subscriber = $this->subscriber();
        $subscriber->delete();

        $this->assertDatabaseCount('newsletter_subscribers', 0);

        Queue::assertPushed(
            UnsubscribeNewsletterFromActiveCampaign::class,
            fn (UnsubscribeNewsletterFromActiveCampaign $job) => $job->contactId === 4242 && $job->deleteContact
        );
    }

    public function test_resubscribing_after_unsubscribe_still_works(): void
    {
        Queue::fake();

        $subscriber = $this->subscriber();
        $this->post($subscriber->unsubscribeUrl())->assertRedirect();

        $this->post(route('newsletter.subscribe'), [
            'email' => $subscriber->email,
            'honeypot' => '',
            'privacy_accepted' => true,
        ])->assertSessionHas('success');

        $this->assertTrue($subscriber->fresh()->isSubscribed());
    }
}
