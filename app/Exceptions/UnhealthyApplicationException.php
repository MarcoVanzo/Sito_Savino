<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un controllo di salute è fallito: il container non è in grado di servire.
 *
 * Sollevarla dal listener di `DiagnosingHealth` fa rispondere 500 a `/up`, che
 * è il segnale su cui App Platform decide di riavviare l'istanza.
 *
 * Non va aggiunta a `ignore_exceptions` in config/sentry.php: il riavvio
 * automatico risolve il sintomo ma nasconde la causa, e senza segnalazione un
 * container che si riavvia in ciclo resta invisibile.
 */
class UnhealthyApplicationException extends RuntimeException
{
    public static function for(string $check, string $reason): self
    {
        return new self("Health check «{$check}» fallito: {$reason}");
    }
}
