<?php

namespace App\Enums;

/**
 * Com'è andato un AvvisoTecnico::invia().
 *
 * Chi ricorda di aver già avvisato (lo stato di `shop:sorveglia`, il
 * silenziatore dell'health check) deve distinguere l'invio fallito da tutti
 * gli altri casi: se lo scrive anche dopo un fallimento, l'avviso non riparte
 * più finché la condizione non cambia.
 */
enum EsitoAvviso
{
    case Inviato;

    /** La stessa condizione è già stata annunciata nella finestra. */
    case Silenziato;

    /** `services.avvisi.email` vuoto: non c'è nessuno da avvisare. */
    case SenzaDestinatari;

    /** Resend o la cache hanno lanciato: l'avviso va ritentato. */
    case Fallito;

    /**
     * Vero se chi chiama può considerare la condizione annunciata. Solo il
     * fallimento lascia aperta la porta al giro successivo.
     */
    public function chiuso(): bool
    {
        return $this !== self::Fallito;
    }
}
