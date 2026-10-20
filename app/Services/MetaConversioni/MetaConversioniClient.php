<?php

namespace App\Services\MetaConversioni;

use Illuminate\Support\Facades\Http;

/**
 * Trasporto verso la Conversions API di Meta (`/{pixel}/events`).
 *
 * Il token e' quello generato in Events Manager per il pixel
 * (`META_CAPI_TOKEN`): e' un segreto e vive solo nelle variabili d'ambiente.
 */
class MetaConversioniClient
{
    private const BASE_URL = 'https://graph.facebook.com/';

    public function __construct(
        private readonly string $token,
        private readonly string $versione,
        private readonly int $timeout,
        private readonly ?string $codiceDiProva = null,
    ) {}

    public static function fromConfig(): self
    {
        $codice = (string) config('services.meta.capi.test_event_code');

        return new self(
            (string) config('services.meta.capi.token'),
            (string) config('services.meta.graph_version', 'v24.0'),
            (int) config('services.meta.timeout', 30),
            $codice !== '' ? $codice : null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $eventi
     * @return array<string, mixed>
     *
     * @throws MetaConversioniException
     */
    public function invia(string $pixelId, array $eventi): array
    {
        if ($this->token === '') {
            throw new MetaConversioniException('Conversions API: META_CAPI_TOKEN mancante.');
        }

        $corpo = ['data' => $eventi];

        // Con il codice di Events Manager → Test Events gli eventi compaiono
        // solo nella scheda di prova e non entrano nelle statistiche.
        if ($this->codiceDiProva !== null) {
            $corpo['test_event_code'] = $this->codiceDiProva;
        }

        try {
            $risposta = Http::acceptJson()
                ->timeout($this->timeout)
                ->withQueryParameters(['access_token' => $this->token])
                ->post(self::BASE_URL.$this->versione.'/'.rawurlencode($pixelId).'/events', $corpo);
        } catch (\Throwable $e) {
            throw new MetaConversioniException('Conversions API non raggiungibile: '.$e->getMessage(), 0, $e);
        }

        $json = $risposta->json();
        $json = is_array($json) ? $json : [];

        if (! $risposta->successful()) {
            $errore = is_array($json['error'] ?? null) ? $json['error'] : [];

            throw new MetaConversioniException(sprintf(
                'Conversions API: HTTP %d, %s',
                $risposta->status(),
                mb_substr((string) ($errore['message'] ?? $risposta->body()), 0, 300),
            ));
        }

        return $json;
    }
}
