<?php

namespace App\Filament\Widgets\Analytics;

use App\Models\AnalyticsSite;
use App\Services\Analytics\WebAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

/**
 * I sei numeri in testa alla pagina Analytics Sito, ciascuno con la variazione
 * rispetto al periodo precedente di pari lunghezza.
 */
class WebKpiWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    /*
     * I valori arrivano da getWidgetData() della pagina. #[Reactive] non è
     * decorativo: senza, Livewire applica i mount param una sola volta e il
     * widget resta fermo al periodo con cui è stato montato — la pagina cambia,
     * i numeri no. È il meccanismo che Filament usa nel suo
     * InteractsWithPageFilters.
     */
    #[Reactive]
    public ?int $siteId = null;

    #[Reactive]
    public int $days = 28;

    /** Palette dell'identità visiva, ripetuta in ordine sulle schede. */
    private const ACCENTI = ['#ED028C', '#003063', '#C9A84C', '#0EA5E9', '#F97316', '#10B981'];

    private int $accento = 0;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $site = $this->siteId === null ? null : AnalyticsSite::query()->find($this->siteId);

        if ($site === null) {
            return [];
        }

        $data = app(WebAnalyticsService::class)->overview($site, $this->days);
        $totals = $data['totals'];
        $deltas = $data['deltas'];
        $daily = $data['daily'];

        return [
            $this->stat('Utenti', number_format((int) $totals['active_users'], 0, ',', '.'), $deltas['active_users'] ?? null, 'heroicon-m-users', $daily, 'active_users'),
            $this->stat('Nuovi utenti', number_format((int) $totals['new_users'], 0, ',', '.'), $deltas['new_users'] ?? null, 'heroicon-m-user-plus', $daily, 'new_users'),
            $this->stat('Sessioni', number_format((int) $totals['sessions'], 0, ',', '.'), $deltas['sessions'] ?? null, 'heroicon-m-cursor-arrow-rays', $daily, 'sessions'),
            $this->stat('Visualizzazioni', number_format((int) $totals['page_views'], 0, ',', '.'), $deltas['page_views'] ?? null, 'heroicon-m-eye', $daily, 'page_views'),
            $this->stat('Durata media', self::duration((int) $totals['avg_session_duration']), $deltas['avg_session_duration'] ?? null, 'heroicon-m-clock', [], null)
                ->description('Durata media di una sessione'),
            $this->stat('Coinvolgimento', number_format((float) $totals['engagement_rate'], 1, ',', '.').'%', $deltas['engagement_rate'] ?? null, 'heroicon-m-hand-raised', [], null)
                ->description('Sessioni con interazione'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $daily
     */
    private function stat(string $label, string $value, ?float $delta, string $icon, array $daily, ?string $key): Stat
    {
        $stat = Stat::make($label, $value)
            ->icon($icon)
            ->extraAttributes(self::accento(self::ACCENTI[$this->accento++ % count(self::ACCENTI)]));

        if ($key !== null && $daily !== []) {
            $stat->chart(array_map(static fn (array $row): float => (float) $row[$key], $daily));
        }

        if ($delta === null) {
            return $stat->color('gray');
        }

        return $stat
            ->description(self::signed($delta).' vs periodo prec.')
            ->descriptionIcon($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($delta >= 0 ? 'success' : 'danger');
    }

    /**
     * Barra colorata in cima alla scheda.
     *
     * Filament non offre un accento per singola statistica e il tema del
     * pannello è condiviso con dashboard e shop: una regola CSS globale li
     * colorerebbe tutti. Lo stile in linea resta confinato a queste pagine.
     */
    private static function accento(string $colore): array
    {
        return ['style' => 'border-top: 3px solid '.$colore.';'];
    }

    private static function signed(float $delta): string
    {
        return ($delta >= 0 ? '+' : '−').number_format(abs($delta), 1, ',', '.').'%';
    }

    private static function duration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
