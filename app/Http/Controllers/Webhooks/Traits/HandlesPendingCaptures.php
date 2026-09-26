<?php

namespace App\Http\Controllers\Webhooks\Traits;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Le catture PayPal che non si concludono subito.
 *
 * Una cattura PENDING (verifica antifrode, eCheck, conto del venditore da
 * confermare) non e' denaro in cassa: veniva registrata come pagata, con
 * conferma d'ordine al cliente e merce pronta a partire, e poteva finire
 * DENIED. Ora l'ordine resta in attesa; lo confermano PAYMENT.CAPTURE.COMPLETED
 * (con lo stesso percorso di ogni incasso) e lo annulla PAYMENT.CAPTURE.DENIED.
 *
 * Si usa solo attraverso HandlesPaymentWebhooks.
 */
trait HandlesPendingCaptures
{
    /**
     * Annota sull'ordine che la cattura e' in sospeso, senza confermarlo.
     *
     * `payment_id` NON si scrive: e' la chiave dell'idempotenza di
     * handlePaymentCompleted, e scriverlo adesso farebbe scartare come
     * "gia' processato" proprio l'evento COMPLETED che deve confermare.
     *
     * @param  array<string, mixed>  $result
     */
    protected function registraCatturaInSospeso(Order $order, array $result): JsonResponse
    {
        $cattura = (string) ($result['payment_id'] ?? '');
        $stato = (string) ($result['capture_status'] ?? 'sconosciuto');

        Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: cattura in sospeso, ordine lasciato in attesa", [
            'order_id' => $order->id,
            'payment_id' => $cattura,
            'capture_status' => $stato,
        ]);

        // Ritorno dal gateway e webhook raccontano la stessa cattura: la
        // segnalazione si fa una volta sola.
        if ($cattura === '' || ! str_contains((string) $order->notes, $cattura)) {
            $this->flagForManualReview(
                $order,
                'payment_pending',
                "Cattura {$cattura} in stato {$stato}: il denaro non e' ancora incassato. L'ordine si conferma da solo quando il gateway completa la cattura; se la rifiuta, si annulla.",
                ['payment_id' => $cattura, 'capture_status' => $stato]
            );
        }

        return response()->json(['message' => 'Payment pending'], 200);
    }

    /**
     * La cattura in sospeso e' stata rifiutata: come un pagamento fallito,
     * l'ordine si annulla e l'OrderObserver rimette merce e coupon.
     *
     * Idempotente: un ordine gia' annullato, o che nel frattempo ha una
     * transazione registrata, non si tocca.
     *
     * @param  array<string, mixed>  $result
     */
    protected function handlePaymentDenied(array $result): JsonResponse
    {
        $order = Order::find($result['order_id'] ?? 0);

        if (! $order) {
            Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: cattura rifiutata su un ordine non trovato", [
                'order_id' => $result['order_id'] ?? null,
                'payment_id' => $result['payment_id'] ?? null,
            ]);

            // 200: ritentare non farebbe comparire l'ordine.
            return response()->json(['message' => 'Ordine non trovato'], 200);
        }

        $annullato = DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->find($order->id);

            if ($order->status !== OrderStatus::Pending || $order->payment_id !== null) {
                return false;
            }

            $order->status = OrderStatus::Cancelled;
            $order->save();

            return true;
        });

        Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: cattura rifiutata", [
            'order_id' => $order->id,
            'payment_id' => $result['payment_id'] ?? null,
            'annullato' => $annullato,
        ]);

        return response()->json(['message' => $annullato ? 'Ordine annullato' : 'Nessuna modifica'], 200);
    }
}
