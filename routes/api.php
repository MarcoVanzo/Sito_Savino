<?php

use App\Http\Controllers\DirettaController;
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

Route::prefix('webhooks')->middleware('throttle:60,1,webhooks')->group(function () {
    Route::post('/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
    // Il nome serve a `paypal:verifica`, che confronta l'indirizzo registrato
    // su PayPal con quello di questo sito.
    Route::post('/paypal', [PayPalWebhookController::class, 'handle'])->name('paypal.webhook');
    // Email non consegnate (rimbalzi, spam, invii falliti): diventano avvisi.
    Route::post('/resend', ResendWebhookController::class)->name('webhooks.resend');
});

/*
|--------------------------------------------------------------------------
| API Routes — Diagnostica
|--------------------------------------------------------------------------
| Gli errori JavaScript del sito passano da qui per arrivare a Sentry
| (resources/js/diagnostica.js). Il limiter `diagnostica` (AppServiceProvider)
| ha un tetto per indirizzo, che tiene a bada un browser in un ciclo d'errore,
| e uno globale, che tiene a bada molti indirizzi insieme: oltre, l'SDK riceve
| 429 e rallenta da solo.
*/

Route::post('/diagnostica', SentryTunnelController::class)
    ->middleware('throttle:diagnostica')
    ->name('diagnostica');

/*
|--------------------------------------------------------------------------
| API Routes — Aste (polling)
|--------------------------------------------------------------------------
| Endpoint leggero per aggiornamenti real-time via polling AJAX.
*/

Route::middleware('throttle:60,1,api.aste')->group(function () {
    Route::get('/aste/{auction}/status', [AuctionController::class, 'status'])
        ->name('api.auctions.status');
});

/*
|--------------------------------------------------------------------------
| API Routes — Diretta con sponsor
|--------------------------------------------------------------------------
| L'ora del server, chiesta una volta all'apertura della diretta: il JSON di
| stato è un file statico e non può dirla. Nessun database. E i contatori
| anonimi del report per lo sponsor.
*/

// 300 al minuto per indirizzo: al fischio d'inizio, dietro la NAT del
// palazzetto o di un operatore mobile, tanti la aprono insieme, e col 429
// restavano sull'orologio del telefono (cornici sfalsate fra spettatori).
Route::get('/diretta/ora', [DirettaController::class, 'ora'])
    ->middleware('throttle:300,1,api.diretta.ora')
    ->name('api.diretta.ora');

// I contatori del report sponsor: anonimi, 120 al minuto per indirizzo. Uno
// spettatore ne manda pochi all'ora, ma dietro la NAT di un operatore
// mobile molti tifosi escono con lo stesso IP, e con 30 i loro eventi si
// perdevano in silenzio (sendBeacon non vede il 429). Le regole su tempo e
// uso della creatività stanno nel controller.
Route::post('/diretta/{game}/eventi', [DirettaController::class, 'eventi'])
    ->middleware('throttle:120,1,api.diretta.eventi')
    ->name('api.diretta.eventi');
