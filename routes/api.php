<?php

use App\Http\Controllers\Shop\AuctionController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
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
    Route::post('/stripe', [StripeWebhookController::class, 'handle']);
    Route::post('/paypal', [PayPalWebhookController::class, 'handle']);
});

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
