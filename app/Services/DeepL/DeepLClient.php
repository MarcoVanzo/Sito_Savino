<?php

namespace App\Services\DeepL;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Traduzioni con le API di DeepL (v2).
 *
 * Le chiavi del piano Free finiscono con `:fx` e rispondono solo su
 * `api-free.deepl.com`; quelle a pagamento su `api.deepl.com`. L'host si
 * sceglie dalla chiave, così passare di piano vuol dire solo cambiarla.
 *
 * L'HTML del corpo delle notizie viaggia con `tag_handling=html`: DeepL
 * traduce il testo e lascia intatti tag, attributi e link.
 */
class DeepLClient
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $targetLang,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        $key = config('services.deepl.key');

        return new self(
            is_string($key) && trim($key) !== '' ? trim($key) : null,
            (string) config('services.deepl.target_lang', 'EN-GB'),
            (int) config('services.deepl.timeout', 30),
        );
    }

    public function configurato(): bool
    {
        return $this->key !== null;
    }

    /**
     * Traduce dall'italiano, nell'ordine ricevuto.
     *
     * @param  list<string>  $testi
     * @return list<string>
     *
     * @throws DeepLException
     */
    public function traduci(array $testi, bool $html = false): array
    {
        if ($testi === []) {
            return [];
        }

        if ($this->key === null) {
            throw new DeepLException('DeepL non configurato: manca DEEPL_API_KEY.');
        }

        $corpo = [
            'text' => $testi,
            'source_lang' => 'IT',
            'target_lang' => $this->targetLang,
        ];

        if ($html) {
            $corpo['tag_handling'] = 'html';
        }

        try {
            $risposta = Http::withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->key])
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->indirizzo(), $corpo);
        } catch (ConnectionException $e) {
            throw new DeepLException('DeepL non raggiungibile: '.$e->getMessage(), previous: $e);
        }

        if ($risposta->failed()) {
            throw new DeepLException(
                sprintf('DeepL ha risposto %d: %s', $risposta->status(), mb_substr($risposta->body(), 0, 200)),
                $risposta->status(),
            );
        }

        $traduzioni = $risposta->json('translations');

        if (! is_array($traduzioni) || count($traduzioni) !== count($testi)) {
            throw new DeepLException('Risposta di DeepL senza le traduzioni attese.');
        }

        return array_values(array_map(
            fn (mixed $riga): string => is_array($riga) ? (string) ($riga['text'] ?? '') : '',
            $traduzioni,
        ));
    }

    private function indirizzo(): string
    {
        $host = str_ends_with((string) $this->key, ':fx') ? 'api-free.deepl.com' : 'api.deepl.com';

        return "https://{$host}/v2/translate";
    }
}
