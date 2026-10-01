<?php

namespace App\Services;

use App\Mail\NuovoOrdineAllaSocieta;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * L'email alla società per ogni acquisto. Parte dove parte la conferma al
 * cliente: a pagamento registrato (webhook e ritorno da Stripe/PayPal) e,
 * per il bonifico, quando l'ordine è fatto e si aspetta l'accredito.
 *
 * Gli indirizzi si scelgono nel pannello (Impostazioni Shop & Aste → Avviso
 * nuovi ordini), separati da virgola; vuoto, non parte niente. Non lancia mai
 * verso chi chiama: arriva da un webhook di pagamento, che non deve fallire
 * per un problema di posta.
 */
class AvvisoNuovoOrdine
{
    public const IMPOSTAZIONE = 'shop.order_notification_emails';

    public function invia(Order $order): void
    {
        $destinatari = self::destinatari();

        if ($destinatari === []) {
            return;
        }

        try {
            Mail::to($destinatari)->queue(new NuovoOrdineAllaSocieta($order));
        } catch (\Throwable $e) {
            report($e);
            Log::error('Avviso del nuovo ordine alla società non accodato', [
                'order_id' => $order->id,
                'errore' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gli indirizzi validi dell'impostazione, senza doppioni.
     *
     * @return list<string>
     */
    public static function destinatari(?string $valore = null): array
    {
        $valore ??= (string) SiteSetting::get(self::IMPOSTAZIONE, '');

        return collect(preg_split('/[\s,;]+/', $valore) ?: [])
            ->map(fn (string $indirizzo) => mb_strtolower(trim($indirizzo)))
            ->filter(fn (string $indirizzo) => filter_var($indirizzo, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
