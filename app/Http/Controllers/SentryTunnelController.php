<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Inoltra a Sentry gli errori JavaScript del sito (resources/js/diagnostica.js).
 *
 * Il browser non parla con Sentry: manda qui l'evento e lo spedisce il server.
 * Così Sentry non riceve l'indirizzo IP del visitatore — è ciò che l'informativa
 * promette — la CSP non si allarga, e un blocco pubblicitario che ferma
 * `sentry.io` non nasconde i guasti.
 *
 * Non è un inoltro aperto: accetta solo buste indirizzate al DSN di questo sito,
 * e le spedisce all'host di quel DSN, mai a uno letto dalla richiesta. Il resto
 * lo limitano la dimensione massima e il throttle della rotta.
 */
class SentryTunnelController extends Controller
{
    /** Un evento con traccia e breadcrumb sta sotto i 100 KB. */
    private const DIMENSIONE_MASSIMA = 200 * 1024;

    public function __invoke(Request $request): Response
    {
        $dsn = (string) config('sentry.dsn');
        $busta = $request->getContent();

        if ($dsn === '' || $busta === '' || strlen($busta) > self::DIMENSIONE_MASSIMA) {
            return response('', 400);
        }

        $intestazione = json_decode(strtok($busta, "\n") ?: '', true);

        if (! is_array($intestazione) || ($intestazione['dsn'] ?? null) !== $dsn) {
            return response('', 400);
        }

        $parti = parse_url($dsn);
        $progetto = trim((string) ($parti['path'] ?? ''), '/');

        if (! isset($parti['host']) || $progetto === '' || ! ctype_digit($progetto)) {
            return response('', 400);
        }

        try {
            $risposta = Http::timeout(5)
                ->withBody($busta, 'application/x-sentry-envelope')
                ->post("https://{$parti['host']}/api/{$progetto}/envelope/");
        } catch (Throwable) {
            // Sentry irraggiungibile: il browser non ha niente da fare con un
            // errore, e riprovare lo spedirebbe due volte.
            return response('', 202);
        }

        return response('', $risposta->successful() ? 200 : 202);
    }
}
