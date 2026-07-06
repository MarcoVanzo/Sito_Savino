<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Traits\HandlesPaymentWebhooks;
use App\Services\Payments\PayPalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController
{
    use HandlesPaymentWebhooks;

    protected string $gatewayName = 'PayPal';

    /**
     * Handle incoming PayPal webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $service = new PayPalPaymentService;
            $result = $service->handleWebhook(
                $request->getContent(),
                [
                    'paypal-auth-algo' => $request->header('PAYPAL-AUTH-ALGO', ''),
                    'paypal-cert-url' => $request->header('PAYPAL-CERT-URL', ''),
                    'paypal-transmission-id' => $request->header('PAYPAL-TRANSMISSION-ID', ''),
                    'paypal-transmission-sig' => $request->header('PAYPAL-TRANSMISSION-SIG', ''),
                    'paypal-transmission-time' => $request->header('PAYPAL-TRANSMISSION-TIME', ''),
                ],
            );
        } catch (\RuntimeException $e) {
            Log::warning('PayPal webhook: verifica fallita', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Verifica fallita'], 400);
        } catch (\Throwable $e) {
            Log::error('PayPal webhook: errore generico', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Errore di verifica'], 400);
        }

        // Ignore non-actionable events
        if ($result['status'] === 'ignored') {
            return response()->json(['message' => 'Evento ignorato'], 200);
        }

        // Handle completed payment
        if ($result['status'] === 'completed') {
            return $this->handlePaymentCompleted($result);
        }

        // Handle refund
        if ($result['status'] === 'refunded') {
            return $this->handleRefund($result);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}
