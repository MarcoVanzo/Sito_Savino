<?php

namespace App\Filament\Widgets\Analytics;

use App\Models\SocialAccount;
use App\Services\Social\SocialAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Visualizzazioni, reach e interazioni giorno per giorno.
 *
 * È la serie ricostruita una chiamata alla volta: appena collegato l'account il
 * grafico è parziale e si riempie da destra, perché i giorni si scaricano dal
 * più recente.
 */
class SocialTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Andamento Instagram';

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    public ?int $accountId = null;

    public int $days = 28;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $account = $this->accountId === null ? null : SocialAccount::query()->find($this->accountId);

        if ($account === null || ! $account->hasInstagram()) {
            return ['datasets' => [], 'labels' => []];
        }

        $daily = app(SocialAnalyticsService::class)->overview($account, $this->days)['daily'];

        $series = static fn (string $key): array => array_map(
            static fn (array $row): int => (int) $row[$key],
            $daily,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Visualizzazioni',
                    'data' => $series('views'),
                    'borderColor' => '#ED028C',
                    'backgroundColor' => 'rgba(237, 2, 140, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Reach',
                    'data' => $series('reach'),
                    'borderColor' => '#003063',
                    'backgroundColor' => 'rgba(0, 48, 99, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Interazioni',
                    'data' => $series('total_interactions'),
                    'borderColor' => '#C9A84C',
                    'backgroundColor' => 'rgba(201, 168, 76, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(
                static fn (array $row): string => Carbon::parse($row['day'])->format('d/m'),
                $daily,
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
