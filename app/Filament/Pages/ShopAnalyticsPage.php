<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Pages\Page;

class ShopAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Analytics Shop';
    protected static ?string $title = 'Analytics Shop';
    protected static ?string $navigationGroup = 'Shop Ufficiale';
    protected static ?int $navigationSort = 10;
    protected static ?string $slug = 'shop/analytics';
    protected static string $view = 'filament.pages.shop-analytics';

    // Periodo di default: ultimi 30 giorni
    public string $period = '30';

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\ShopKpiWidget::class,
            Widgets\SalesTrendWidget::class,
            Widgets\TopProductsWidget::class,
            Widgets\OrdersByStatusWidget::class,
            Widgets\PaymentMethodsWidget::class,
            Widgets\PageViewsWidget::class,
            Widgets\CustomersWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('period')
                ->label('Periodo')
                ->icon('heroicon-m-calendar-days')
                ->form([
                    \Filament\Forms\Components\Select::make('period')
                        ->label('Periodo')
                        ->options([
                            '7' => 'Ultimi 7 giorni',
                            '30' => 'Ultimi 30 giorni',
                            '90' => 'Ultimi 90 giorni',
                            '365' => 'Ultimo anno',
                        ])
                        ->default($this->period)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->period = $data['period'];
                    $this->dispatch('updatePeriod', period: $this->period);
                }),
        ];
    }

    protected function getHeaderWidgetsData(): array
    {
        return [
            'period' => $this->period,
        ];
    }
}
