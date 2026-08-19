<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'source' => 'footer',
            'synced_to_ac' => true,
            'ac_contact_id' => $this->faker->numberBetween(1, 99999),
            'subscribed_at' => now(),
        ];
    }

    /** Iscritto che ActiveCampaign non ha ancora ricevuto. */
    public function notSynced(): static
    {
        return $this->state(fn (): array => ['synced_to_ac' => false, 'ac_contact_id' => null]);
    }
}
