<?php

use App\Http\Controllers\SentryTunnelController;
use App\Http\Controllers\Shop\AuctionController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use App\Http\Controllers\Webhooks\ResendWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Webhook
|--------------------------------------------------------------------------
| Route per i webhook dei gateway di pagamento.
| Non protette da CSRF (gestito via firma del payload).
*/

Route::prefix('webhooks')->middleware('throttle:60,1')->group(function () {
    Route::post('/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
    // Il nome serve a `paypal:verifica`, che confronta l'indirizzo registrato
    // su PayPal con quello di questo sito.
    Route::post('/paypal', [PayPalWebhookController::class, 'handle'])->name('paypal.webhook');
    // Email non consegnate (rimbalzi, spam, invii falliti): diventano avvisi.
    Route::post('/resend', ResendWebhookController::class)->name('resend.webhook');
});

/*
|--------------------------------------------------------------------------
| API Routes — Diagnostica
|--------------------------------------------------------------------------
| Gli errori JavaScript del sito passano da qui per arrivare a Sentry
| (resources/js/diagnostica.js). Il limite per indirizzo tiene a bada un
| browser in un ciclo d'errore: oltre, l'SDK riceve 429 e rallenta da solo.
*/

Route::post('/diagnostica', SentryTunnelController::class)
    ->middleware('throttle:30,1')
    ->name('diagnostica');

/*
|--------------------------------------------------------------------------
| API Routes — Aste (polling)
|--------------------------------------------------------------------------
| Endpoint leggero per aggiornamenti real-time via polling AJAX.
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/aste/{auction}/status', [AuctionController::class, 'status'])
        ->name('api.auctions.status');
});
