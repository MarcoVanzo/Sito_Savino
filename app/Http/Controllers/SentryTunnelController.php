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
 * lo limitano la dimensione massima e il limiter `diagnostica`
 * (AppServiceProvider: un tetto per indirizzo e uno globale, perché ogni
 * richiesta tiene occupato un processo PHP mentre aspetta Sentry).
 *
 * Della busta passano solo gli item `event`: sessioni, replay, allegati,
 * transazioni e profili non servono a trovare un guasto, pesano sulla quota
 * condivisa con gli errori del server e possono portare dati del visitatore
 * che il sito non ha dichiarato. L'SDK è configurato per non mandarli; questo
 * è il controllo lato server per chi manda una busta a mano.
 */
class SentryTunnelController extends Controller
{
    /** Un evento con traccia e breadcrumb sta sotto i 100 KB. */
    private const DIMENSIONE_MASSIMA = 200 * 1024;

    public function __invoke(Request $request): Response
    {
        // Il corpo si legge solo dopo aver guardato quanto dichiara di pesare.
        if ((int) $request->header('Content-Length', '0') > self::DIMENSIONE_MASSIMA) {
            return response('', 413);
        }

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

        $busta = self::soloGliEventi($busta);

        if ($busta === null) {
            return response('', 400);
        }

        if ($busta === '') {
            // Niente da inoltrare: per il browser la busta è consegnata.
            return response('', 202);
        }

        try {
            // Tempi corti: ogni secondo di attesa è un processo PHP tolto
            // alle pagine del sito, e un evento perso non è un guasto.
            $risposta = Http::connectTimeout(1)
                ->timeout(3)
                ->withBody($busta, 'application/x-sentry-envelope')
                ->post("https://{$parti['host']}/api/{$progetto}/envelope/");
        } catch (Throwable) {
            // Sentry irraggiungibile: il browser non ha niente da fare con un
            // errore, e riprovare lo spedirebbe due volte.
            return response('', 202);
        }

        // Il 429 di Sentry torna al browser con le sue intestazioni: è così
        // che l'SDK rallenta. Mascherato da 202, un browser in un ciclo
        // d'errore continuerebbe a spedire e a consumare la quota del
        // progetto, che è la stessa degli errori del server.
        if ($risposta->status() === 429) {
            return response('', 429)->withHeaders(array_filter([
                'X-Sentry-Rate-Limits' => $risposta->header('X-Sentry-Rate-Limits'),
                'Retry-After' => $risposta->header('Retry-After'),
            ]));
        }

        return response('', $risposta->successful() ? 200 : 202);
    }

    /**
     * La busta con i soli item `event`: la stessa stringa se non c'è niente
     * da togliere, '' se non resta nessun evento, null se è malformata.
     *
     * Formato: una riga d'intestazione, poi per ogni item una riga JSON con
     * `type` e, se presente, `length` (il payload è lungo esattamente tanti
     * byte, e può contenere a capo); senza `length` il payload arriva fino al
     * prossimo a capo.
     */
    public static function soloGliEventi(string $busta): ?string
    {
        $lunghezza = strlen($busta);
        $fine = strpos($busta, "\n");

        if ($fine === false) {
            return '';
        }

        $risultato = substr($busta, 0, $fine)."\n";
        $pos = $fine + 1;
        $eventi = 0;
        $scartati = 0;

        while ($pos < $lunghezza) {
            $fineRiga = strpos($busta, "\n", $pos);
            $fineRiga = $fineRiga === false ? $lunghezza : $fineRiga;
            $rigaIntestazione = substr($busta, $pos, $fineRiga - $pos);
            $pos = $fineRiga + 1;

            if (trim($rigaIntestazione) === '') {
                continue;
            }

            $intestazione = json_decode($rigaIntestazione, true);

            if (! is_array($intestazione)) {
                return null;
            }

            if (isset($intestazione['length'])) {
                if (! is_int($intestazione['length']) || $intestazione['length'] < 0) {
                    return null;
                }

                $contenuto = substr($busta, $pos, $intestazione['length']);
                $pos += $intestazione['length'];

                if ($pos < $lunghezza && $busta[$pos] === "\n") {
                    $pos++;
                }
            } else {
                $fineContenuto = strpos($busta, "\n", $pos);
                $fineContenuto = $fineContenuto === false ? $lunghezza : $fineContenuto;
                $contenuto = substr($busta, $pos, $fineContenuto - $pos);
                $pos = $fineContenuto + 1;
            }

            if (($intestazione['type'] ?? null) === 'event') {
                $eventi++;
                $risultato .= $rigaIntestazione."\n".$contenuto."\n";
            } else {
                $scartati++;
            }
        }

        if ($eventi === 0) {
            return '';
        }

        return $scartati === 0 ? $busta : $risultato;
    }
}
