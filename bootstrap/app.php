<?php

use App\Http\Middleware\CachePublicResponse;
use App\Http\Middleware\EnsureAuctionsEnabled;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureVerifiedPayment;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreviewBasicAuth;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
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

        // DigitalOcean App Platform: trust all proxies but only forwarded headers
        // (DO doesn't publish proxy IP ranges, so we must trust '*' but restrict headers)
        //
        // X-Forwarded-Host è volutamente ESCLUSO: fidandosene, chiunque poteva
        // forgiare quell'header e far generare a Laravel URL assoluti verso un
        // dominio arbitrario (in primis i link di reset password, che finivano
        // così su un host controllato dall'attaccante). DigitalOcean inoltra
        // già l'Host originale, quindi non serve.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_PORT,
        );

        // Seconda linea di difesa contro l'host header injection: si accettano
        // solo richieste il cui Host corrisponde ad APP_URL o a un suo
        // sottodominio. Il valore è risolto a runtime (una closure) perché a
        // questo punto della configurazione la config non è ancora caricata.
        // Il middleware TrustHosts di Laravel si auto-disattiva in ambiente
        // `local` e durante i test, dove l'host varia (localhost, *.test, ...).
        // Attenzione: una lista vuota per Symfony significa "nessuna
        // restrizione", quindi un valore non interpretabile disattiva la difesa
        // in silenzio invece di segnalarlo. Su App Platform APP_URL vale
        // `${APP_DOMAIN}`, cioè un dominio SENZA schema, su cui parse_url()
        // restituisce null: va normalizzato, altrimenti questa protezione non
        // entra mai in funzione in produzione.
        $middleware->trustHosts(
            at: static function (): array {
                /** @var list<string> $configured */
                $configured = (array) config('app.trusted_hosts', []);

                if ($configured !== []) {
                    return $configured;
                }

                $url = trim((string) config('app.url'));

                if ($url === '') {
                    return [];
                }

                $host = parse_url($url, PHP_URL_HOST)
                    ?: parse_url('https://'.ltrim($url, '/'), PHP_URL_HOST);

                return is_string($host) && $host !== '' ? [$host] : [];
            },
            subdomains: true,
        );

        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);

        $middleware->alias([
            'auctions.enabled' => EnsureAuctionsEnabled::class,
            'verified.payment' => EnsureVerifiedPayment::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // Renderizza errori HTTP come pagine Inertia con il design del sito
            if (in_array($response->getStatusCode(), [403, 404, 500, 503])
                && ! $request->is('api/*', 'admin/*', 'filament/*', 'livewire/*')
                && ! app()->environment('local')
            ) {
                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
