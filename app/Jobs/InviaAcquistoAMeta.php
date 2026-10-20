<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\MetaConversioni\MetaConversioniClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * L'acquisto mandato a Meta dal server (Conversions API), accanto al
 * `Purchase` del pixel nel browser.
 *
 * Parte dal pagamento confermato (HandlesPaymentWebhooks, Stripe e PayPal) e
 * solo se l'ordine e' stato fatto con il consenso di marketing
 * (`orders.consenso_marketing`). L'`event_id` e' lo stesso del pixel
 * (`purchase-<numero d'ordine>`, meta-pixel.js): Meta conta una volta sola
 * l'acquisto arrivato da tutti e due.
 *
 * Spento per impostazione (`META_CAPI_ENABLED=false`): prima di accenderlo
 * vanno aggiornate privacy e cookie policy.
 */
class InviaAcquistoAMeta implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public int $uniqueFor = 3600;

    public function __construct(public Order $order) {}

    /**
     * Accoda l'invio se la Conversions API e' accesa e l'ordine lo consente.
     */
    public static function seServe(Order $order): void
    {
        if (! self::accesa() || ! $order->consenso_marketing || $order->meta_acquisto_inviato_at !== null) {
            return;
        }

        self::dispatch($order);
    }

    public static function accesa(): bool
    {
        return (bool) config('services.meta.capi.enabled')
            && (string) config('services.meta.capi.token') !== ''
            && self::pixelId() !== '';
    }

    public static function eventId(Order $order): string
    {
        return 'purchase-'.$order->order_number;
    }

    public function uniqueId(): string
    {
        return (string) $this->order->id;
    }

    public function handle(): void
    {
        // Riletto: fra l'accodamento e l'esecuzione puo' essere gia' partito
        // (un secondo incasso dello stesso ordine) o essere stato cancellato.
        $order = $this->order->fresh(['items.product', 'user']);

        if (! $order || ! $order->consenso_marketing || $order->meta_acquisto_inviato_at !== null || ! self::accesa()) {
            return;
        }

        MetaConversioniClient::fromConfig()->invia(self::pixelId(), [$this->evento($order)]);

        $order->forceFill(['meta_acquisto_inviato_at' => now()])->saveQuietly();
    }

    /**
     * L'ultimo tentativo fallito arriva su Sentry: un acquisto non attribuito
     * non si vede da nessun'altra parte.
     */
    public function failed(\Throwable $e): void
    {
        report($e);

        Log::error('Conversions API: acquisto non inviato a Meta', [
            'order_id' => $this->order->id,
            'error' => mb_substr($e->getMessage(), 0, 300),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function evento(Order $order): array
    {
        $righe = $order->items;

        return [
            'event_name' => 'Purchase',
            'event_time' => ($order->paid_at ?? now())->getTimestamp(),
            'event_id' => self::eventId($order),
            'action_source' => 'website',
            'event_source_url' => $order->successUrl(),
            'user_data' => array_filter([
                'em' => $this->impronte([$order->user->email ?? $order->guest_email], fn (string $email): string => mb_strtolower(trim($email))),
                'ph' => $this->impronte([$order->phone ?? $order->guest_phone], fn (string $telefono): string => $this->telefono($telefono, (string) $order->country)),
                'fbp' => $order->meta_fbp,
                'fbc' => $order->meta_fbc,
                'client_user_agent' => $order->meta_user_agent,
            ], fn (mixed $valore): bool => $valore !== null && $valore !== [] && $valore !== ''),
            'custom_data' => [
                'currency' => 'EUR',
                'value' => (float) $order->total_price,
                'order_id' => (string) $order->order_number,
                'content_type' => 'product',
                'content_ids' => $righe->map(fn ($riga): string => (string) $riga->product_id)->values()->all(),
                'contents' => $righe->map(fn ($riga): array => [
                    'id' => (string) $riga->product_id,
                    'quantity' => (int) $riga->quantity,
                    'item_price' => (float) $riga->price_at_time_of_purchase,
                ])->values()->all(),
                'num_items' => (int) $righe->sum('quantity'),
            ],
        ];
    }

    /**
     * SHA-256 dei valori normalizzati, come chiede Meta: email e telefono non
     * lasciano il sito in chiaro.
     *
     * @param  list<string|null>  $valori
     * @param  callable(string): string  $normalizza
     * @return list<string>
     */
    private function impronte(array $valori, callable $normalizza): array
    {
        $impronte = [];

        foreach ($valori as $valore) {
            $normalizzato = $valore === null ? '' : $normalizza($valore);

            if ($normalizzato !== '') {
                $impronte[] = hash('sha256', $normalizzato);
            }
        }

        return $impronte;
    }

    /**
     * Solo cifre e con il prefisso internazionale. Un numero italiano scritto
     * senza prefisso (3xx o 0xx, fino a 11 cifre) prende il 39.
     */
    private function telefono(string $telefono, string $paese): string
    {
        $cifre = preg_replace('/\D+/', '', $telefono) ?? '';

        if (str_starts_with($cifre, '00')) {
            return substr($cifre, 2);
        }

        if (str_starts_with(trim($telefono), '+')) {
            return $cifre;
        }

        if (strtoupper($paese) === 'IT' && preg_match('/^[03]\d{5,10}$/', $cifre) === 1) {
            return '39'.$cifre;
        }

        return $cifre;
    }

    private static function pixelId(): string
    {
        return trim((string) SiteSetting::get('analytics.meta_pixel_id', ''));
    }
}
