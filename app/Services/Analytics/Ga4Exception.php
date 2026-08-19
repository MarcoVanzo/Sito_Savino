<?php

namespace App\Services\Analytics;

/**
 * Errore della GA4 Data API tradotto in una causa che si può mostrare.
 *
 * "HTTP 403" non dice a nessuno cosa fare. Le cause previste:
 *
 *   not_configured  service account assente dalla configurazione
 *   auth_failed     il token OAuth2 non si ottiene (chiave rotta, orologio storto)
 *   not_authorized  il service account non è Visualizzatore sulla property
 *   api_disabled    la Data API non è abilitata nel progetto Google
 *   bad_property    id property inesistente o malformato
 *   quota           quota della Data API esaurita
 *   unavailable     rete, timeout o 5xx
 */
class Ga4Exception extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $httpStatus = 0,
    ) {
        parent::__construct($message);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            'not_configured' => 'Service account Google non configurato: manca GA4_SERVICE_ACCOUNT_JSON.',
            'auth_failed' => 'Autenticazione con Google fallita: controlla il service account.',
            'not_authorized' => 'Il service account non ha accesso a questa proprietà. Aggiungilo come Visualizzatore in Google Analytics → Amministrazione → Gestione accessi alla proprietà.',
            'api_disabled' => 'La Google Analytics Data API non è abilitata nel progetto Google del service account.',
            'bad_property' => 'ID proprietà GA4 non valido o inesistente.',
            'quota' => 'Quota della Google Analytics Data API esaurita: riprova più tardi.',
            default => 'Google Analytics non è raggiungibile in questo momento.',
        };
    }
}
