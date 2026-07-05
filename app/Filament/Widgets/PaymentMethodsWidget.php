<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class PaymentMethodsWidget extends ChartWidget
{
    protected static ?string $heading = 'Metodi di Pagamento';
    protected static ?string $pollingInterval = null;
    protected static ?int $sort = 6;
    protected static bool $isDiscovered = false;
    protected static ?string $maxHeight = '300px';

    /**
     * Stati che indicano ordini effettivamente pagati.
     */
    private const PAID_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filterFormData['period'] ?? 30);
        $from = now()->subDays($days)->startOfDay();

        $gatewayColors = [
            PaymentGateway::Stripe->value => '#635bff',
            PaymentGateway::PayPal->value => '#003087',
            PaymentGateway::BankTransfer->value => '#C9A84C',
        ];

        $counts = Order::whereIn('status', self::PAID_STATUSES)
            ->where('created_at', '>=', $from)
            ->whereNotNull('payment_gateway')
            ->selectRaw('payment_gateway, COUNT(*) as total')
            ->groupBy('payment_gateway')
            ->pluck('total', 'payment_gateway')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = [];

        foreach (PaymentGateway::cases() as $gateway) {
            $count = $counts[$gateway->value] ?? 0;
            if ($count > 0) {
                $labels[] = $gateway->getLabel();
                $data[] = $count;
                $colors[] = $gatewayColors[$gateway->value] ?? '#94a3b8';
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
