<?php

namespace App\Filament\Widgets\Analytics;

use App\Models\SocialAccount;
use App\Services\Social\SocialAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Gli otto numeri di Instagram in testa alla pagina Social Analytics.
 *
 * Alcuni valori possono essere null e non zero: Meta non fornisce reach e
 * nuovi follower a tutti gli account. "n/d" e "0" vogliono dire cose molto
 * diverse e la differenza si vede.
 */
class SocialKpiWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    public ?int $accountId = null;

    public int $days = 28;

    /** Le otto schede Instagram, tutte nel rosa dell'identità social. */
    private const ACCENTO = '#ED028C';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $account = $this->accountId === null ? null : SocialAccount::query()->find($this->accountId);

        if ($account === null || ! $account->hasInstagram()) {
            return [];
        }

        $data = app(SocialAnalyticsService::class)->overview($account, $this->days);
        $totals = $data['totals'];
        $profile = $data['profile'];
        $daily = $data['daily'];

        if ($totals === []) {
            return [];
        }

        $followers = (int) ($profile['followers_count'] ?? 0);
        $delta = $totals['follower_delta'];
        $reach = $totals['reach'];
        $engaged = (int) $totals['accounts_engaged'];

        return [
            Stat::make('Follower', self::number($followers))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-users')
                ->description($delta === null ? 'Variazione non disponibile' : self::signed($delta).' nel periodo')
                ->descriptionIcon($delta !== null && $delta < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color(self::coloreDelta($delta))
                ->chart(array_map(static fn (array $row): float => (float) $row['follower_count'], $daily)),

            Stat::make('Visualizzazioni', self::number($totals['views']))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-eye')
                ->chart(array_map(static fn (array $row): float => (float) $row['views'], $daily)),

            Stat::make('Account raggiunti', $reach === null ? 'n/d' : self::number($reach))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-signal')
                ->description('Reach del periodo')
                ->chart(array_map(static fn (array $row): float => (float) $row['reach'], $daily)),

            Stat::make('Interazioni', self::number($totals['total_interactions']))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-hand-raised')
                ->description($reach ? self::percent((int) $totals['total_interactions'], $reach).' del reach' : null)
                ->chart(array_map(static fn (array $row): float => (float) $row['total_interactions'], $daily)),

            Stat::make('Account che hanno interagito', self::number($engaged))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-user-circle')
                ->description($followers > 0 ? self::percent($engaged, $followers).' dei follower' : null),

            Stat::make('Nuovi follower', $totals['new_follows'] === null ? 'n/d' : self::number($totals['new_follows']))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-user-plus')
                ->description($totals['unfollows'] === null ? null : '−'.self::number($totals['unfollows']).' persi')
                ->color($totals['new_follows'] === null ? 'gray' : 'success'),

            Stat::make('Tap sui link del profilo', self::number($totals['profile_links_taps']))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-link'),

            Stat::make('Post pubblicati', self::number((int) ($profile['media_count'] ?? 0)))
                ->extraAttributes(self::accento())
                ->icon('heroicon-m-photo')
                ->description('Storico completo'),
        ];
    }

    /**
     * Barra colorata in cima alla scheda: il tema del pannello è condiviso con
     * dashboard e shop, quindi l'accento resta in linea invece che in una
     * regola CSS globale.
     *
     * @return array<string, string>
     */
    private static function accento(): array
    {
        return ['style' => 'border-top: 3px solid '.self::ACCENTO.';'];
    }

    private static function number(int|float|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private static function percent(int $part, int $total): string
    {
        return $total > 0 ? number_format($part / $total * 100, 1, ',', '.').'%' : '0%';
    }

    private static function signed(int $value): string
    {
        return ($value >= 0 ? '+' : '−').self::number(abs($value));
    }

    /**
     * Grigio quando il confronto con il periodo precedente non c'e' (la serie
     * non e' abbastanza lunga), altrimenti verde o rosso.
     */
    private static function coloreDelta(?int $delta): string
    {
        if ($delta === null) {
            return 'gray';
        }

        return $delta >= 0 ? 'success' : 'danger';
    }
}
