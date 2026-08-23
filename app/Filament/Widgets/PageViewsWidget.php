<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ShopEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;
use Livewire\Attributes\Reactive;

class PageViewsWidget extends ChartWidget
{
    protected static ?string $heading = 'Prodotti Più Visti';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 5;

    protected static bool $isDiscovered = false;

    protected static ?string $maxHeight = '300px';

    /**
     * Periodo in giorni, passato da ShopAnalyticsPage::getWidgetData(). #[Reactive]
     * è indispensabile: senza, Livewire monta il widget una volta sola e il
     * cambio di periodo sulla pagina non arriverebbe mai qui.
     */
    #[Reactive]
    public int $period = 30;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = $this->period;
        $from = now()->subDays($days)->startOfDay();

        $topViews = ShopEvent::views()
            ->where('viewable_type', (new Product)->getMorphClass())
            ->where('created_at', '>=', $from)
            ->selectRaw('viewable_id, COUNT(*) as view_count')
            ->groupBy('viewable_id')
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        // Carica i nomi dei prodotti
        $productIds = $topViews->pluck('viewable_id')->toArray();
        $products = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $labels = [];
        $data = [];

        foreach ($topViews as $event) {
            $name = $products[$event->viewable_id] ?? 'Prodotto #'.$event->viewable_id;
            $labels[] = Str::limit($name, 20);
            $data[] = $event->view_count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Visualizzazioni',
                    'data' => $data,
                    'backgroundColor' => '#003063',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
