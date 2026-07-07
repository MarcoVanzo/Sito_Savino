<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\ActiveCampaignService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncNewsletterToActiveCampaign implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    /**
     * Durata massima del lock di unicità (5 minuti).
     * Evita che un lock rimanga bloccato se il worker muore.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public NewsletterSubscriber $subscriber
    ) {}

    /**
     * Unique ID per evitare job duplicati per lo stesso subscriber.
     */
    public function uniqueId(): string
    {
        return (string) $this->subscriber->id;
    }

    public function handle(ActiveCampaignService $service): void
    {
        if (! $service->isConfigured()) {
            Log::info('ActiveCampaign non configurato, sincronizzazione saltata', [
                'subscriber_id' => $this->subscriber->id,
            ]);

            return;
        }

        // Step 1: Sincronizza il contatto
        $contactId = $service->syncContact(
            $this->subscriber->email,
            $this->subscriber->first_name,
        );

        if (! $contactId) {
            Log::error('ActiveCampaign: impossibile creare/aggiornare contatto', [
                'subscriber_id' => $this->subscriber->id,
                'email' => $this->subscriber->email,
            ]);

            throw new \RuntimeException('Impossibile sincronizzare il contatto con ActiveCampaign');
        }

        // Step 2: Iscrivi alla lista
        $subscribed = $service->subscribeToList($contactId);

        if (! $subscribed) {
            Log::error('ActiveCampaign: impossibile iscrivere alla lista', [
                'subscriber_id' => $this->subscriber->id,
                'contact_id' => $contactId,
            ]);

            throw new \RuntimeException('Impossibile iscrivere il contatto alla lista ActiveCampaign');
        }

        // Step 3: Aggiorna il record locale
        $this->subscriber->update([
            'synced_to_ac' => true,
            'ac_contact_id' => $contactId,
        ]);

        Log::info('ActiveCampaign: sincronizzazione completata', [
            'subscriber_id' => $this->subscriber->id,
            'contact_id' => $contactId,
        ]);
    }

    /**
     * Gestione failure permanente dopo tutti i retry.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ActiveCampaign: sincronizzazione fallita permanentemente', [
            'subscriber_id' => $this->subscriber->id,
            'email' => $this->subscriber->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
