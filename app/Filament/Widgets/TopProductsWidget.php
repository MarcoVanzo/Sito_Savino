<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class TopProductsWidget extends Widget
{
    protected static string $view = 'filament.widgets.top-products';
    protected static ?int $sort = 3;
    protected static bool $isDiscovered = false;
    protected int|string|array $columnSpan = 'full';

    /**
     * Query dei top 10 prodotti per fatturato generato nel periodo selezionato.
     */
    public function getTopProducts(): array
    {
        $days = (int) ($this->filterFormData['period'] ?? 30);
        $from = now()->subDays($days)->startOfDay();

        return OrderItem::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(quantity * price_at_time_of_purchase) as total_revenue'),
            )
            ->whereHas('order', function ($q) use ($from) {
                $q->whereIn('status', [
                    OrderStatus::Paid,
                    OrderStatus::Processing,
                    OrderStatus::Shipped,
                    OrderStatus::Delivered,
                ])
                ->where('created_at', '>=', $from);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->with('product:id,name')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Prodotto rimosso',
                'qty' => (int) $item->total_qty,
                'revenue' => number_format($item->total_revenue, 2, ',', '.'),
            ])
            ->toArray();
    }
}
