<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActiveCampaignService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $listId;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.activecampaign.url', ''), '/');
        $this->apiKey = config('services.activecampaign.key', '');
        $this->listId = (int) config('services.activecampaign.list_id', 0);
    }

    /**
     * Check if ActiveCampaign is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiKey) && $this->listId > 0;
    }

    /**
     * Le tre chiamate all'API usano gli stessi header, lo stesso timeout e la
     * stessa politica di ritentativo: cambia solo l'endpoint e il corpo.
     */
    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Api-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(15)->retry(2, 1000, throw: false);
    }

    /**
     * Create or update a contact (idempotent via contact/sync).
     * Returns the contact ID on success, null on failure.
     */
    /**
     * @param  array<int, string>  $campi  valori dei campi personalizzati, per id del campo
     */
    public function syncContact(string $email, ?string $firstName = null, ?string $lastName = null, array $campi = []): ?int
    {
        if (! $this->isConfigured()) {
            Log::warning('ActiveCampaign: API non configurata. Skipping syncContact.');

            return null;
        }

        $contactData = ['email' => $email];

        if ($firstName !== null) {
            $contactData['firstName'] = $firstName;
        }

        if ($lastName !== null) {
            $contactData['lastName'] = $lastName;
        }

        if ($campi !== []) {
            $contactData['fieldValues'] = collect($campi)
                ->map(fn (string $valore, int $campo) => ['field' => (string) $campo, 'value' => $valore])
                ->values()
                ->all();
        }

        $response = $this->client()->post($this->baseUrl.'/api/3/contact/sync', [
            'contact' => $contactData,
        ]);

        if ($response->successful()) {
            $contactId = $response->json('contact.id');

            Log::info('ActiveCampaign: contatto sincronizzato', [
                'email' => $email,
                'contact_id' => $contactId,
            ]);

            return $contactId ? (int) $contactId : null;
        }

        Log::error('ActiveCampaign: errore syncContact', [
            'email' => $email,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        return null;
    }

    /**
     * Subscribe a contact to the configured list.
     */
    public function subscribeToList(int $contactId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $response = $this->client()->post($this->baseUrl.'/api/3/contactLists', [
            'contactList' => [
                'list' => $this->listId,
                'contact' => $contactId,
                'status' => 1,
            ],
        ]);

        if ($response->successful()) {
            Log::info('ActiveCampaign: contatto iscritto alla lista', [
                'contact_id' => $contactId,
                'list_id' => $this->listId,
            ]);

            return true;
        }

        Log::error('ActiveCampaign: errore subscribeToList', [
            'contact_id' => $contactId,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        return false;
    }

    /**
     * Disiscrive un contatto dalla lista configurata.
     *
     * Stesso endpoint dell'iscrizione: cambia lo status (1 = iscritto,
     * 2 = disiscritto). Il contatto resta in anagrafica su ActiveCampaign,
     * che è ciò che serve per non ricontattarlo per errore.
     */
    public function unsubscribeFromList(int $contactId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $response = $this->client()->post($this->baseUrl.'/api/3/contactLists', [
            'contactList' => [
                'list' => $this->listId,
                'contact' => $contactId,
                'status' => 2,
            ],
        ]);

        if ($response->successful()) {
            Log::info('ActiveCampaign: contatto disiscritto dalla lista', [
                'contact_id' => $contactId,
                'list_id' => $this->listId,
            ]);

            return true;
        }

        Log::error('ActiveCampaign: errore unsubscribeFromList', [
            'contact_id' => $contactId,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        return false;
    }

    /**
     * Cancella definitivamente un contatto da ActiveCampaign.
     *
     * Serve alla richiesta di cancellazione dei dati: la sola disiscrizione
     * lascia l'indirizzo in anagrafica.
     */
    public function deleteContact(int $contactId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $response = Http::withHeaders([
            'Api-Token' => $this->apiKey,
        ])->timeout(15)->retry(2, 1000, throw: false)->delete($this->baseUrl.'/api/3/contacts/'.$contactId);

        // 404 = contatto già assente: l'esito voluto è comunque raggiunto.
        if ($response->successful() || $response->status() === 404) {
            Log::info('ActiveCampaign: contatto cancellato', ['contact_id' => $contactId]);

            return true;
        }

        Log::error('ActiveCampaign: errore deleteContact', [
            'contact_id' => $contactId,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        return false;
    }

    /**
     * L'id del campo personalizzato in cui sta il link alle preferenze della
     * newsletter (per esempio `PREFERENZE_URL`, che nel modello si scrive
     * `%PREFERENZE_URL%`). Null se non configurato: il link resta allora solo
     * quello di disiscrizione di ActiveCampaign.
     */
    public function campoPreferenze(): ?int
    {
        $id = (int) config('services.activecampaign.campo_preferenze', 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Mette al contatto il tag degli iscritti che hanno revocato il
     * tracciamento (`services.activecampaign.tag_senza_tracciamento`). Il tag
     * si cerca per nome e si crea se manca. Idempotente: ActiveCampaign
     * risponde 422 se il contatto ha gia' il tag, ed e' l'esito voluto.
     */
    public function aggiungiTagSenzaTracciamento(int $contactId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $nome = (string) config('services.activecampaign.tag_senza_tracciamento', 'senza-tracciamento');
        $tagId = $this->idDelTag($nome);

        if ($tagId === null) {
            return false;
        }

        $response = $this->client()->post($this->baseUrl.'/api/3/contactTags', [
            'contactTag' => ['contact' => $contactId, 'tag' => $tagId],
        ]);

        if ($response->successful() || $response->status() === 422) {
            return true;
        }

        Log::error('ActiveCampaign: errore aggiungiTagSenzaTracciamento', [
            'contact_id' => $contactId,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        return false;
    }

    private function idDelTag(string $nome): ?int
    {
        $trovati = $this->client()->get($this->baseUrl.'/api/3/tags', ['search' => $nome]);

        foreach ((array) $trovati->json('tags', []) as $tag) {
            if (($tag['tag'] ?? null) === $nome) {
                return (int) $tag['id'];
            }
        }

        $creato = $this->client()->post($this->baseUrl.'/api/3/tags', [
            'tag' => ['tag' => $nome, 'tagType' => 'contact', 'description' => 'Newsletter senza pixel né link tracciati'],
        ]);

        $id = $creato->json('tag.id');

        if (! $creato->successful() || ! $id) {
            Log::error('ActiveCampaign: impossibile creare il tag', ['status' => $creato->status()]);

            return null;
        }

        return (int) $id;
    }
}
