<?php

namespace App\Jobs;

use App\Services\ActiveCampaignException;
use App\Services\ActiveCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Propaga ad ActiveCampaign la disiscrizione (o la cancellazione) decisa sul
 * sito. Non riceve il model ma i suoi dati: il record locale può essere già
 * stato eliminato quando il job gira.
 */
class UnsubscribeNewsletterFromActiveCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(
        public int $contactId,
        public string $email,
        public bool $deleteContact = false,
    ) {}

    public function handle(ActiveCampaignService $service): void
    {
        if (! $service->isConfigured()) {
            Log::info('ActiveCampaign non configurato, disiscrizione saltata', [
                'contact_id' => $this->contactId,
            ]);

            return;
        }

        $done = $this->deleteContact
            ? $service->deleteContact($this->contactId)
            : $service->unsubscribeFromList($this->contactId);

        if (! $done) {
            throw new ActiveCampaignException('Impossibile propagare la disiscrizione ad ActiveCampaign');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('ActiveCampaign: disiscrizione fallita permanentemente', [
            'contact_id' => $this->contactId,
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
