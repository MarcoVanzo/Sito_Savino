<?php

namespace App\Enums;

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

    public function getIcon(): string
    {
        return match ($this) {
            self::Stripe => 'heroicon-o-credit-card',
            self::PayPal => 'heroicon-o-banknotes',
            self::BankTransfer => 'heroicon-o-building-library',
        };
    }
}
