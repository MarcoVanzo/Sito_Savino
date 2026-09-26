<?php

namespace App\Http\Controllers\Webhooks\Traits;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Mail\RefundConfirmation;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * I rimborsi notificati dal gateway (Stripe `charge.refunded`, PayPal
 * `PAYMENT.CAPTURE.REFUNDED`).
 *
 * Si usa solo attraverso HandlesPaymentWebhooks, da cui prende il nome del
 * gateway, il saldo dei movimenti e la segnalazione per revisione manuale.
 *
 * Entrambi i gateway notificano OGNI rimborso, anche parziale. Trattarli
 * tutti come totali significava, per un rimborso delle sole spese di
 * spedizione: ordine Rimborsato, merce rimessa in giacenza mentre e' ancora
 * dal cliente (e vendibile due volte), email di rimborso completo. Ora solo
 * il rimborso totale cambia stato; quello parziale registra l'importo
 * cumulato su `orders.refunded_amount` e lascia l'ordine dov'e'.
 */
trait HandlesRefundWebhooks
{
    /**
     * Handle refund webhook event.
     *
     * $result porta, oltre a `payment_id`:
     * - `totale`: true/false se il gateway lo dice esplicitamente, null se non si sa;
     * - `refunded_amount`: rimborsato CUMULATO in euro, se noto.
     *
     * @param  array<string, mixed>  $result
     */
    protected function handleRefund(array $result): JsonResponse
    {
        $order = Order::where('payment_id', $result['payment_id'])->first();

        if (! $order) {
            Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: ordine non trovato per rimborso", [
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Ordine non trovato'], 404);
        }

        try {
            $esito = DB::transaction(function () use ($order, $result) {
                // Lock the order row to prevent concurrent webhook processing
                $order = Order::lockForUpdate()->find($order->id);

                // Idempotency check: if already refunded, skip processing
                if ($order->status === OrderStatus::Refunded) {
                    Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: rimborso già processato (idempotenza)", [
                        'order_id' => $order->id,
                    ]);

                    return 'replay';
                }

                $cumulato = is_numeric($result['refunded_amount'] ?? null) ? round((float) $result['refunded_amount'], 2) : null;
                $totale = $this->rimborsoTotale($order, $result['totale'] ?? null, $cumulato);

                if ($totale === null) {
                    // Senza sapere quanto e' stato restituito non si rimette
                    // merce in giacenza: meglio un ordine da sistemare a mano
                    // che uno rimborsato per errore.
                    $this->flagForManualReview(
                        $order,
                        'refund_unknown_amount',
                        "Rimborso notificato su {$result['payment_id']} senza importo leggibile: verificare sul gateway se e' totale e aggiornare l'ordine.",
                        ['payment_id' => $result['payment_id']]
                    );

                    return 'unknown';
                }

                if (! $totale) {
                    // Il cumulato e' idempotente: lo stesso evento ripetuto
                    // riscrive la stessa cifra, uno arrivato fuori ordine non
                    // la fa scendere.
                    if ($cumulato !== null && $cumulato > (float) ($order->refunded_amount ?? 0)) {
                        $order->forceFill(['refunded_amount' => $cumulato])->save();
                    }

                    return 'partial';
                }

                $order->status = OrderStatus::Refunded;
                $order->refunded_amount = $cumulato ?? $order->total_price;
                $order->save();

                // OrderObserver::restoreStock salta il ripristino se esiste già un
                // Adjustment sull'ordine: capita quando l'ordine era stato annullato
                // (stock ripristinato), poi pagato in ritardo (stock riscaricato) e
                // infine rimborsato. Qui si riporta comunque il saldo dei movimenti
                // a zero; se l'observer ha già fatto il suo lavoro non c'è nulla da fare.
                $this->reconcileStockAfterRefund($order);

                return 'refunded';
            });

            if ($esito === 'replay') {
                return response()->json(['message' => 'Already refunded'], 200);
            }

            if ($esito === 'unknown') {
                return response()->json(['message' => 'Refund recorded, manual review required'], 200);
            }

            if ($esito === 'partial') {
                Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: rimborso parziale registrato", [
                    'order_id' => $order->id,
                    'payment_id' => $result['payment_id'],
                    'refunded_amount' => $result['refunded_amount'] ?? null,
                ]);

                return response()->json(['message' => 'Rimborso parziale registrato'], 200);
            }

            // Refresh the order to get updated data from the transaction
            $order->refresh();

            // Send refund confirmation email (outside transaction)
            $recipientEmail = $order->user->email ?? $order->guest_email;
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)->queue(new RefundConfirmation($order));
                } catch (\Throwable $e) {
                    report($e);

                    Log::error('Errore invio email rimborso', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: rimborso registrato", [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Rimborso processato'], 200);

        } catch (\Throwable $e) {
            report($e);

            Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: errore processamento rimborso", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore interno'], 500);
        }
    }

    /**
     * Il rimborso copre tutto l'ordine?
     *
     * Vale quello che dice il gateway (Stripe `refunded`, PayPal lo stato della
     * cattura); se non lo dice, il cumulato confrontato col totale
     * dell'ordine. Null quando non c'e' nessuno dei due.
     */
    private function rimborsoTotale(Order $order, mixed $dichiarato, ?float $cumulato): ?bool
    {
        if (is_bool($dichiarato)) {
            return $dichiarato;
        }

        if ($cumulato === null) {
            return null;
        }

        return $cumulato >= round((float) $order->total_price, 2);
    }

    /**
     * Riporta a zero il saldo dei movimenti dell'ordine dopo un rimborso,
     * coprendo i casi in cui OrderObserver::restoreStock si è auto-escluso.
     */
    private function reconcileStockAfterRefund(Order $order): void
    {
        $outstanding = array_filter(
            $this->stockBalanceFor($order),
            fn (array $row) => $row['net'] < 0
        );

        foreach ($outstanding as $row) {
            StockMovement::create([
                'product_id' => $row['product_id'],
                'product_variant_id' => $row['product_variant_id'],
                'order_id' => $order->id,
                'quantity' => abs($row['net']),
                'type' => StockMovementType::Adjustment,
                'notes' => "Ripristino Ordine #{$order->id} — rimborso",
            ]);
        }

        if (! empty($outstanding)) {
            Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: stock riconciliato dopo rimborso", [
                'order_id' => $order->id,
            ]);
        }
    }
}
