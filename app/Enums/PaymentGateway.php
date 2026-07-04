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

    public function getIcon(): string
    {
        return match ($this) {
            self::Stripe => 'heroicon-o-credit-card',
            self::PayPal => 'heroicon-o-banknotes',
            self::BankTransfer => 'heroicon-o-building-library',
        };
    }
}
