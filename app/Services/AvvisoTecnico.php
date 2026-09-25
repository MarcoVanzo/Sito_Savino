<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email per i guasti che non possono aspettare il prossimo accesso al pannello.
 *
 * Le notifiche di AdminNotificationService finiscono nella campanella del
 * pannello: si vedono solo entrando, e un pianificatore fermo o un pagamento da
 * rivedere non possono aspettare che qualcuno entri. Questa classe manda la
 * stessa cosa per email a `services.avvisi.email`.
 *
 * Tre scelte non ovvie:
 *
 * - **Invio sincrono, mai in coda.** Fra i guasti da segnalare c'è proprio la
 *   coda ferma e il job fallito: un avviso messo in coda arriverebbe quando il
 *   problema è già risolto, o mai.
 * - **Non lancia.** Si chiama da un webhook di pagamento, dall'health check e
 *   dal listener dei job falliti: un Resend irraggiungibile non deve far
 *   fallire nessuno di questi. L'errore va a Sentry e basta.
 * - **Silenziatore per chiave.** La stessa condizione non produce più di una
 *   email nella finestra indicata; `add()` è atomico, quindi regge anche fra
 *   web, worker e scheduler, che condividono la cache.
 */
class AvvisoTecnico
{
    /**
     * @param  string  $chiave  identifica la condizione, per il silenziatore
     * @param  int  $silenzioSecondi  0 = nessun silenziatore
     */
    public function invia(string $oggetto, string $testo, string $chiave, int $silenzioSecondi = 3600): bool
    {
        $destinatari = self::destinatari();

        if ($destinatari === []) {
            return false;
        }

        $silenziatore = 'avviso-tecnico:'.$chiave;

        // Anche il silenziatore sta dentro il try: con la cache su database
        // `add()` può lanciare, e chi chiama non deve accorgersene.
        try {
            if ($silenzioSecondi > 0 && ! Cache::add($silenziatore, true, $silenzioSecondi)) {
                return false;
            }

            Mail::raw($testo."\n\n— ".config('app.url'), function (Message $message) use ($destinatari, $oggetto): void {
                $message->to($destinatari)->subject('[Sito Savino] '.$oggetto);
            });
        } catch (Throwable $e) {
            report($e);

            // Un invio fallito non conta: altrimenti il silenziatore
            // tacerebbe la stessa condizione per tutta la finestra, proprio
            // quando la posta torna a funzionare.
            if ($silenzioSecondi > 0) {
                try {
                    Cache::forget($silenziatore);
                } catch (Throwable) {
                    // la cache è giù: niente da liberare
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function destinatari(): array
    {
        return collect(explode(',', (string) config('services.avvisi.email')))
            ->map(fn (string $indirizzo): string => trim($indirizzo))
            ->filter(fn (string $indirizzo): bool => filter_var($indirizzo, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }
}
