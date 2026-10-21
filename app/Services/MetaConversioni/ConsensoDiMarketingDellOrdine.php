<?php

namespace App\Services\MetaConversioni;

use App\Jobs\InviaAcquistoAMeta;
use App\Models\ConsensoCookie;
use App\Models\Order;

/**
 * Il consenso di marketing del banner, fotografato sull'ordine al checkout.
 *
 * Il browser manda il riferimento della sua scelta (`consensi_cookie.riferimento`)
 * e, se il marketing e' concesso, `_fbp` e `_fbc`. Il sito non si fida della
 * casella del browser: vale l'ultima riga del registro per quel riferimento,
 * sull'informativa di oggi e non piu' vecchia di dodici mesi (la durata di
 * `consenso.js`). Senza consenso non si scrive niente per Meta.
 */
class ConsensoDiMarketingDellOrdine
{
    private const DURATA_GIORNI = 365;

    public function registra(Order $order, ?string $riferimento, ?string $fbp, ?string $fbc, ?string $userAgent): void
    {
        if (! $this->eConcesso($riferimento)) {
            return;
        }

        $order->forceFill([
            'consenso_marketing' => true,
            'consenso_cookie_riferimento' => $riferimento,
        ]);

        // Gli identificativi di Meta servono solo a mandare l'acquisto: con la
        // Conversions API spenta non si raccolgono. Dopo l'invio li cancella
        // InviaAcquistoAMeta, e quelli rimasti (un bonifico, un pagamento mai
        // arrivato) `meta:dimentica-gli-identificativi` dopo 7 giorni.
        if (InviaAcquistoAMeta::accesa()) {
            $order->forceFill([
                'meta_fbp' => $this->identificativo($fbp, 'fb.'),
                'meta_fbc' => $this->identificativo($fbc, 'fb.'),
                'meta_user_agent' => $userAgent !== null && $userAgent !== '' ? mb_substr($userAgent, 0, 1000) : null,
            ]);
        }

        $order->save();
    }

    public function eConcesso(?string $riferimento): bool
    {
        if ($riferimento === null || $riferimento === '') {
            return false;
        }

        $ultima = ConsensoCookie::query()
            ->where('riferimento', $riferimento)
            ->latest('id')
            ->first();

        return $ultima !== null
            && $ultima->marketing
            && $ultima->versione === ConsensoCookie::VERSIONE
            && $ultima->created_at->greaterThanOrEqualTo(now()->subDays(self::DURATA_GIORNI));
    }

    /**
     * `_fbp` e `_fbc` hanno la forma `fb.<n>.<tempo>.<valore>`: qualunque
     * altra cosa arrivi dal browser non si salva.
     */
    private function identificativo(?string $valore, string $prefisso): ?string
    {
        if ($valore === null || ! str_starts_with($valore, $prefisso) || mb_strlen($valore) > 255) {
            return null;
        }

        return preg_match('/^fb\.\d+\.\d+\.[A-Za-z0-9_\-.]+$/', $valore) === 1 ? $valore : null;
    }
}
