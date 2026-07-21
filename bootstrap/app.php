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
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_PORT,
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
