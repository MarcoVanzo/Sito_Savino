<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('enums.order_status.pending'),
            self::Processing => __('enums.order_status.processing'),
            self::Paid => __('enums.order_status.paid'),
            self::Shipped => __('enums.order_status.shipped'),
            self::Delivered => __('enums.order_status.delivered'),
            self::Cancelled => __('enums.order_status.cancelled'),
            self::Refunded => __('enums.order_status.refunded'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Paid => 'success',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
            self::Refunded => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Processing => 'heroicon-o-arrow-path',
            self::Paid => 'heroicon-o-check-circle',
            self::Shipped => 'heroicon-o-truck',
            self::Delivered => 'heroicon-o-check-badge',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Refunded => 'heroicon-o-arrow-uturn-left',
        };
    }
}
