<?php

namespace App\Http\Middleware;

use App\Support\HostFidati;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chi apre il sito da un indirizzo secondario finisce su quello di APP_URL.
 *
 * Dal 1 ottobre 2026 il sito è `savinodelbenevolley.it`, ma risponde ancora
 * su `seashell-app-47mmf.ondigitalocean.app` (webhook di Resend, Stripe e
 * PayPal) e su `www.`. Su quegli host la pagina si disegnava ma non
 * funzionava: in produzione `forceRootUrl` scrive ogni indirizzo generato con
 * il dominio, quindi le chiamate axios di `route()`, i moduli e le visite
 * Inertia partivano verso un'altra origine e il CORS le fermava ("AxiosError:
 * Network Error" in Sentry, 01/10/2026). In più erano copie della stessa
 * pagina agli occhi di Google.
 *
 * Si sposta solo la navigazione (GET e HEAD): le POST dei webhook restano
 * dove sono, e `api/*` e `/up` non si toccano mai — la sorveglianza interroga
 * `/up` anche sull'indirizzo di App Platform. Gli host spostati sono solo
 * `www.` del dominio e `*.ondigitalocean.app`, e mai quello di APP_URL: se
 * il dominio tornasse quello di App Platform (com'è successo con la #144) il
 * middleware non fa niente, invece di rimbalzare il sito su sé stesso.
 */
class PortaSullIndirizzoDelSito
{
    /** @var list<string> */
    private const SEMPRE_DOVE_SONO = ['api/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $principale = HostFidati::hostDi((string) config('app.url'));
        $radice = HostFidati::radicePubblica((string) config('app.url'));
        $host = strtolower($request->getHost());

        if ($principale === null || $radice === null || ! $this->daSpostare($host, strtolower($principale))) {
            return $next($request);
        }

        if ($request->is(...self::SEMPRE_DOVE_SONO)) {
            return $next($request);
        }

        $destinazione = $radice.$request->getRequestUri();

        // Una visita Inertia è una XHR: un 301 verso un'altra origine la
        // farebbe morire sul CORS, che è proprio il guasto da togliere. Il 409
        // con X-Inertia-Location fa navigare il browser per intero.
        if ($request->header('X-Inertia')) {
            return Inertia::location($destinazione);
        }

        return redirect()->away($destinazione, 301);
    }

    private function daSpostare(string $host, string $principale): bool
    {
        if ($host === $principale) {
            return false;
        }

        return $host === 'www.'.$principale || str_ends_with($host, '.ondigitalocean.app');
    }
}
