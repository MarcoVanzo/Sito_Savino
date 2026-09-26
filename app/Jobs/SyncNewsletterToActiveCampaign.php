<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\ActiveCampaignException;
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
        // Doppio opt-in: finché il proprietario della casella non ha cliccato
        // il link di conferma, l'indirizzo non esce dal sito. È il controllo
        // di ultima istanza — lo rispettano già il modulo, il comando di
        // risincronizzazione e il pannello — perché qualunque strada nuova
        // che accodi questo job passi di qui.
        if (! $this->subscriber->fresh()?->haConfermato()) {
            Log::info('Newsletter: iscrizione non confermata, sincronizzazione saltata', [
                'subscriber_id' => $this->subscriber->id,
            ]);

            return;
        }

        if (! $service->isConfigured()) {
            Log::info('ActiveCampaign non configurato, sincronizzazione saltata', [
                'subscriber_id' => $this->subscriber->id,
            ]);

            return;
        }

        // Step 1: Sincronizza il contatto, con il link alle preferenze nel
        // campo personalizzato che il footer del modello usa.
        $campi = [];
        if ($campo = $service->campoPreferenze()) {
            $campi[$campo] = $this->subscriber->preferenzeUrl('it');
        }

        $contactId = $service->syncContact(
            $this->subscriber->email,
            $this->subscriber->first_name,
            null,
            $campi,
        );

        if (! $contactId) {
            Log::error('ActiveCampaign: impossibile creare/aggiornare contatto', [
                'subscriber_id' => $this->subscriber->id,
            ]);

            throw new ActiveCampaignException('Impossibile sincronizzare il contatto con ActiveCampaign');
        }

        // Step 2: Iscrivi alla lista
        $subscribed = $service->subscribeToList($contactId);

        if (! $subscribed) {
            Log::error('ActiveCampaign: impossibile iscrivere alla lista', [
                'subscriber_id' => $this->subscriber->id,
                'contact_id' => $contactId,
            ]);

            throw new ActiveCampaignException('Impossibile iscrivere il contatto alla lista ActiveCampaign');
        }

        // Step 3: chi ha revocato il tracciamento entra nel segmento delle
        // campagne senza pixel. Un errore qui fa ritentare il job: una
        // revoca che non arriva ad ActiveCampaign e' una promessa non tenuta.
        if (! $this->subscriber->fresh()->tracciamentoAttivo() && ! $service->aggiungiTagSenzaTracciamento($contactId)) {
            throw new ActiveCampaignException('Impossibile segnare il contatto come senza tracciamento');
        }

        // Step 4: Aggiorna il record locale
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
            'error' => $exception->getMessage(),
        ]);
    }
}
