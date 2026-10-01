<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Traits\HandlesPaymentWebhooks;
use App\Models\Order;
use App\Models\User;
use App\Services\AvvisoTecnico;
use App\Services\Payments\StripeCustomerService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController
{
    use HandlesPaymentWebhooks;

    protected function getGatewayName(): string
    {
        return 'Stripe';
    }

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

        // Handle setup session completed (payment method verification)
        if ($result['status'] === 'setup_completed') {
            return $this->handleSetupCompleted($result);
        }

        // Pagamento incassato. Un pagamento a esito differito (SEPA,
        // bonifico) passa dallo stesso punto, che lo riconosce e lascia
        // l'ordine in attesa.
        if (in_array($result['status'], ['completed', 'pending'], true)) {
            return $this->handlePaymentCompleted($result);
        }

        // Pagamento differito fallito: l'ordine si annulla.
        if ($result['status'] === 'denied') {
            return $this->handlePaymentDenied($result);
        }

        if ($result['status'] === 'dispute') {
            return $this->handleDispute($result);
        }

        // Handle refund
        if ($result['status'] === 'refunded') {
            return $this->handleRefund($result);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * Handle setup session completed — verifica metodo di pagamento per aste.
     */
    private function handleSetupCompleted(array $result): JsonResponse
    {
        $userId = $result['user_id'] ?? null;

        if (! $userId) {
            Log::warning('Stripe webhook setup_completed: user_id mancante nei metadata');

            return response()->json(['message' => 'user_id mancante'], 200);
        }

        $user = User::find($userId);

        if (! $user) {
            Log::warning("Stripe webhook setup_completed: User #{$userId} non trovato");

            return response()->json(['message' => 'Utente non trovato'], 200);
        }

        $stripeCustomerService = app(StripeCustomerService::class);
        $stripeCustomerService->handleSetupComplete($user);

        return response()->json(['message' => 'Metodo di pagamento verificato'], 200);
    }

    /**
     * Il cliente ha contestato l'addebito (chargeback).
     *
     * Avviso per email, non solo la campanella del pannello: c'e' un termine
     * per mandare le prove dalla dashboard di Stripe, e senza risposta la
     * contestazione e' persa.
     */
    private function handleDispute(array $result): JsonResponse
    {
        $order = $result['payment_id'] ? Order::where('payment_id', $result['payment_id'])->first() : null;

        $importo = $result['amount'] !== null ? number_format((float) $result['amount'], 2, ',', '.').' €' : 'importo non indicato';
        $scadenza = $result['due_by'] !== null
            ? now()->setTimestamp($result['due_by'])->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : 'non indicata';

        $testo = "Contestazione {$result['dispute_id']} su Stripe ({$importo}, motivo: ".($result['reason'] ?? 'non indicato').").\n"
            .'Ordine: '.($order !== null ? $order->order_number : 'non trovato, pagamento '.($result['payment_id'] ?? '?')).".\n"
            ."Termine per rispondere con le prove: {$scadenza}.\n"
            .'Si risponde dalla dashboard di Stripe, sezione Contestazioni.';

        // Stripe ripete l'evento finche' non riceve un 200: la nota si scrive
        // una volta sola.
        if ($order !== null && ! str_contains((string) $order->notes, (string) $result['dispute_id'])) {
            $this->flagForManualReview($order, 'dispute', $testo, [
                'dispute_id' => $result['dispute_id'],
                'payment_id' => $result['payment_id'],
            ]);
        }

        Log::warning('Stripe webhook: contestazione aperta', [
            'dispute_id' => $result['dispute_id'],
            'order_id' => $order?->id,
        ]);

        app(AvvisoTecnico::class)->invia(
            'Contestazione di un pagamento Stripe',
            $testo,
            'stripe-disputa:'.$result['dispute_id'],
            86400,
        );

        return response()->json(['message' => 'Contestazione registrata'], 200);
    }
}
