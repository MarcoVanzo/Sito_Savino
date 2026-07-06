<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Traits\HandlesPaymentWebhooks;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController
{
    use HandlesPaymentWebhooks;

    protected string $gatewayName = 'Stripe';

    /**
     * Handle incoming Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $service = new StripePaymentService;
            $result = $service->handleWebhook(
                $request->getContent(),
                ['stripe-signature' => $request->header('Stripe-Signature', '')],
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: firma non valida', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Firma non valida'], 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: errore verifica', [
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
