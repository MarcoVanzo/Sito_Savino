<?php

namespace App\Enums;

use App\Models\SiteSetting;
use Filament\Support\Contracts\HasLabel;

enum PaymentGateway: string implements HasLabel
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stripe => 'Carta di Credito (Stripe)',
            self::PayPal => 'PayPal',
            self::BankTransfer => 'Bonifico Bancario',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stripe => 'primary',
            self::PayPal => 'info',
            self::BankTransfer => 'warning',
        };
    }

    /**
     * Il gateway ha le credenziali per lavorare.
     *
     * Il metodo di pagamento si sceglie dal pannello, ma le chiavi stanno
     * nell'ambiente: offrire una carta di credito senza le chiavi di Stripe
     * significa mandare il cliente in errore DOPO aver creato l'ordine e
     * riservato la merce. Meglio non mostrarlo affatto.
     */
    public function configurato(): bool
    {
        return match ($this) {
            self::Stripe => filled(config('services.stripe.secret')),
            self::PayPal => filled(config('services.paypal.client_id'))
                && filled(config('services.paypal.client_secret')),
            self::BankTransfer => true,
        };
    }

    /**
     * I metodi che il checkout mostra davvero: attivi dal pannello
     * (`shop.active_payment_gateways`) e con le credenziali nell'ambiente.
     *
     * @return list<self>
     */
    public static function offertiAlCheckout(): array
    {
        $attivi = (string) SiteSetting::get('shop.active_payment_gateways', 'stripe,paypal,bank_transfer');

        return collect(explode(',', $attivi))
            ->map(fn (string $g): ?self => self::tryFrom(trim($g)))
            ->filter(fn (?self $g): bool => $g !== null && $g->configurato())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * I metodi con cui il vincitore di un'asta può pagare: gli stessi del
     * checkout dello shop, bonifico compreso (richiesta della società,
     * 30/09/2026). Il bonifico non sta nelle 48 ore del termine: sceglierlo
     * sposta il termine del vincitore alla scadenza del bonifico
     * (`terminePerIlBonifico`), come per un ordine dello shop.
     *
     * @return list<self>
     */
    public static function offertiAlleAste(): array
    {
        return self::offertiAlCheckout();
    }

    /**
     * I giorni concessi per un bonifico, dall'ordine: quelli scritti al
     * cliente nell'email di conferma (`shop.bank_transfer_expiry_days`).
     * L'annullo automatico e il promemoria leggono lo stesso numero: prima
     * erano sette giorni cablati mentre l'email ne prometteva cinque.
     */
    public static function giorniPerIlBonifico(): int
    {
        $giorni = (int) SiteSetting::get('shop.bank_transfer_expiry_days', 7);

        return $giorni > 0 ? $giorni : 7;
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Stripe => 'heroicon-o-credit-card',
            self::PayPal => 'heroicon-o-banknotes',
            self::BankTransfer => 'heroicon-o-building-library',
        };
    }
}
