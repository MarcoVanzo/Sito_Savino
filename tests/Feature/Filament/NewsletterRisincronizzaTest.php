<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers;
use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Risincronizza" non rimette in lista su ActiveCampaign chi ne è uscito:
 * il consenso revocato vale anche per il pannello.
 */
class NewsletterRisincronizzaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());
        Queue::fake();
    }

    public function test_l_azione_singola_non_compare_per_un_disiscritto(): void
    {
        $disiscritto = NewsletterSubscriber::factory()->create(['synced_to_ac' => false, 'unsubscribed_at' => now()]);
        $attivo = NewsletterSubscriber::factory()->create(['synced_to_ac' => false, 'unsubscribed_at' => null]);

        Livewire::test(ListNewsletterSubscribers::class)
            ->removeTableFilters()
            ->assertTableActionHidden('retry_sync', $disiscritto)
            ->assertTableActionVisible('retry_sync', $attivo);
    }

    public function test_l_azione_massiva_salta_i_disiscritti(): void
    {
        $disiscritto = NewsletterSubscriber::factory()->create(['synced_to_ac' => false, 'unsubscribed_at' => now()]);
        $attivo = NewsletterSubscriber::factory()->create(['synced_to_ac' => false, 'unsubscribed_at' => null]);

        Livewire::test(ListNewsletterSubscribers::class)
            ->removeTableFilters()
            ->callTableBulkAction('retry_sync_bulk', [$disiscritto, $attivo]);

        Queue::assertPushed(SyncNewsletterToActiveCampaign::class, 1);
        Queue::assertPushed(SyncNewsletterToActiveCampaign::class, fn ($job) => $job->subscriber->is($attivo));
    }
}
