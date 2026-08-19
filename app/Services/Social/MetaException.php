<?php

namespace App\Services\Social;

/**
 * Errore della Graph API tradotto in una causa che si può mostrare e su cui si
 * può decidere.
 *
 * La distinzione che conta davvero è fra `invalid_metric` e tutto il resto:
 * quando Meta rifiuta una metrica (deprecata, o non disponibile per quel tipo
 * di account) ha senso ritentare senza quella metrica, mentre su token morto o
 * rate limit ritentare significa solo consumare chiamate mentre Meta sta già
 * dicendo di no.
 *
 *   not_connected  nessun token per questo account
 *   token_expired  il token non è più valido: serve ricollegare l'account
 *   permission     manca un permesso sul collegamento (codici 10/200/272)
 *   rate_limited   troppe chiamate: si riprende più tardi
 *   invalid_metric metrica o parametro rifiutato (codice 100)
 *   unavailable    rete, timeout o 5xx
 */
class MetaException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $httpStatus = 0,
        public readonly int $graphCode = 0,
    ) {
        parent::__construct($message);
    }

    /**
     * Errore della Graph API → causa.
     *
     * @param  array<string, mixed>  $error  il contenuto della chiave "error"
     */
    public static function fromGraphError(array $error, int $httpStatus): self
    {
        $code = (int) ($error['code'] ?? 0);
        $message = (string) ($error['message'] ?? 'Errore Graph API');

        $reason = match (true) {
            $code === 190 => 'token_expired',
            in_array($code, [4, 17, 32, 613], true) => 'rate_limited',
            in_array($code, [10, 200, 272, 299], true) => 'permission',
            $code === 100 => 'invalid_metric',
            $httpStatus >= 500 => 'unavailable',
            default => 'unavailable',
        };

        return new self($reason, $message, $httpStatus, $code);
    }

    /**
     * Vale la pena ritentare senza il pezzo che Meta ha rifiutato?
     *
     * Solo per `invalid_metric`: su token o rate limit un secondo tentativo
     * peggiora le cose.
     */
    public function isRetryableWithoutMetric(): bool
    {
        return $this->reason === 'invalid_metric';
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            'not_connected' => 'Account Meta non collegato.',
            'token_expired' => 'Il collegamento con Meta è scaduto: ricollega l\'account.',
            'permission' => 'Al collegamento Meta manca un permesso necessario: ricollega l\'account concedendo tutti i permessi richiesti.',
            'rate_limited' => 'Meta ha temporaneamente limitato le richieste: i dati si aggiornano più tardi.',
            'invalid_metric' => 'Meta non fornisce una delle metriche richieste per questo account.',
            default => 'Meta non è raggiungibile in questo momento.',
        };
    }
}
