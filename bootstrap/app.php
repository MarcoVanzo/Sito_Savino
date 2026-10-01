<?php

use App\Http\Middleware\CachePublicResponse;
use App\Http\Middleware\EnsureAuctionsEnabled;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureVerifiedPayment;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PortaSullIndirizzoDelSito;
use App\Http\Middleware\PreviewBasicAuth;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\UsaLIpDelClienteDiDigitalOcean;
use App\Support\HostFidati;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // PreviewBasicAuth deve precedere CachePublicResponse: altrimenti una
        // risposta in cache viene servita prima del controllo credenziali,
        // rendendo pubbliche le pagine del sito ancora in preview.
        $middleware->web(prepend: [
            PreviewBasicAuth::class,
            CachePublicResponse::class,
        ]);
        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            // Lega la sessione all'hash della password, come già fa il pannello
            // Filament: senza, un cambio password o un reset non invalidava le
            // altre sessioni del sito pubblico e una sessione rubata restava
            // valida a tempo indeterminato. Le sessioni già attive non vengono
            // interrotte: al primo passaggio l'hash viene semplicemente salvato.
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePasswordIsChanged::class,
        ]);

        // Prima di tutto, anche di TrustProxies: l'IP del visitatore arriva da
        // DO-Connecting-IP (vedi il middleware).
        $middleware->prepend(UsaLIpDelClienteDiDigitalOcean::class);

        // DigitalOcean App Platform: trust all proxies but only forwarded headers
        // (DO doesn't publish proxy IP ranges, so we must trust '*' but restrict headers)
        //
        // X-Forwarded-For da solo non basta: con i proxy fidati `*` l'IP del
        // client sarebbe quello che il client stesso scrive nell'header, e i
        // limiti per IP non limiterebbero nessuno. L'IP vero lo mette
        // UsaLIpDelClienteDiDigitalOcean da DO-Connecting-IP, scartando XFF;
        // XFF resta solo come ripiego se quell'header mancasse. Proto e Port
        // servono a riconoscere l'HTTPS terminato dal proxy.
        //
        // X-Forwarded-Host è volutamente ESCLUSO: fidandosene, chiunque poteva
        // forgiare quell'header e far generare a Laravel URL assoluti verso un
        // dominio arbitrario (in primis i link di reset password, che finivano
        // così su un host controllato dall'attaccante). DigitalOcean inoltra
        // già l'Host originale, quindi non serve.
        $middleware->trustProxies(
            at: '*',
            // X-Forwarded-For resta fidato solo come ripiego: quando c'è
            // DO-Connecting-IP lo scarta UsaLIpDelClienteDiDigitalOcean.
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_PORT,
        );

        // Seconda linea di difesa contro l'host header injection: si accettano
        // solo richieste il cui Host corrisponde ad APP_URL (o a TRUSTED_HOSTS)
        // o a un loro sottodominio, più gli IP della rete interna per la sonda
        // di App Platform. Le espressioni le costruisce HostFidati, ancorate ed
        // escapate. Il valore è risolto a runtime (una closure) perché a
        // questo punto della configurazione la config non è ancora caricata.
        // Il middleware TrustHosts di Laravel si auto-disattiva in ambiente
        // `local` e durante i test, dove l'host varia (localhost, *.test, ...).
        // `subdomains: false` perché i sottodomini sono già nelle espressioni:
        // quella che aggiungerebbe Laravel è ridondante e, con APP_URL senza
        // schema, assente.
        $middleware->trustHosts(
            at: static fn (): array => HostFidati::patterns(
                array_values(array_map('strval', (array) config('app.trusted_hosts', []))),
                (string) config('app.url'),
            ),
            subdomains: false,
        );

        // In coda ai globali, quindi dopo TrustHosts: un Host non fidato è
        // già stato respinto. Copre anche il pannello, che non passa da `web`.
        $middleware->append(PortaSullIndirizzoDelSito::class);

        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);

        $middleware->alias([
            'auctions.enabled' => EnsureAuctionsEnabled::class,
            'verified.payment' => EnsureVerifiedPayment::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Segnalazione degli errori a Sentry. Senza DSN configurato il client è
        // inerte, quindi in locale e nei test non parte nessuna richiesta di rete.
        // Cosa NON arriva qui è deciso da `ignore_exceptions` in config/sentry.php.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // Sessione scaduta o troppi invii da un modulo del sito: si torna
            // alla pagina, con il modulo ancora compilato e un messaggio,
            // invece della finestra con l'HTML grezzo che Inertia mostra per
            // una risposta che non è una pagina. La risposta al 419 porta già
            // la sessione nuova, quindi il secondo invio passa.
            if (in_array($response->getStatusCode(), [419, 429], true)
                && $request->header('X-Inertia')
                && ! $request->is('api/*', 'admin/*', 'filament/*', 'livewire/*')
            ) {
                return back()->with('error', __($response->getStatusCode() === 419
                    ? 'messages.errori.pagina_scaduta'
                    : 'messages.errori.troppi_tentativi'));
            }

            // Renderizza errori HTTP come pagine Inertia con il design del sito
            if (in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503])
                && ! $request->is('api/*', 'admin/*', 'filament/*', 'livewire/*')
                && ! app()->environment('local')
            ) {
                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                    // Un link firmato scaduto o alterato (conferma della
                    // newsletter, disiscrizione, ricevuta di recesso) non è un
                    // "accesso negato": chi lo apre deve sapere cosa fare.
                    'messaggio' => $exception instanceof InvalidSignatureException
                        ? __('messages.errori.link_scaduto')
                        : null,
                ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
