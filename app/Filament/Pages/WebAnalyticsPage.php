<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\RestrictsAccessByRole;
use App\Filament\Widgets\Analytics\WebKpiWidget;
use App\Filament\Widgets\Analytics\WebTrendWidget;
use App\Models\AnalyticsSite;
use App\Services\Analytics\WebAnalyticsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Traffico del sito, pagina per pagina (Google Analytics 4).
 *
 * I siti misurati sono più di uno, quindi la pagina lavora sempre su un sito
 * selezionato. La lettura vera la fa {@see WebAnalyticsService}, che tiene i
 * dati in cache un'ora: il selettore del periodo e i widget qui sopra possono
 * quindi chiedere gli stessi numeri più volte senza moltiplicare le chiamate.
 */
class WebAnalyticsPage extends Page
{
    use RestrictsAccessByRole;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics Sito';

    protected static ?string $title = 'Analytics Sito';

    protected static ?string $navigationGroup = 'Comunicazione';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'analytics/sito';

    protected static string $view = 'filament.pages.web-analytics';

    public ?int $siteId = null;

    public int $days = 28;

    public function mount(): void
    {
        $this->siteId = AnalyticsSite::query()->ordered()->value('id');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WebKpiWidget::class,
            WebTrendWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['siteId' => $this->siteId, 'days' => $this->days];
    }

    protected function getHeaderActions(): array
    {
        $sites = AnalyticsSite::query()->ordered()->pluck('name', 'id');

        return array_values(array_filter([
            $sites->count() > 1
                ? Action::make('site')
                    ->label('Sito')
                    ->icon('heroicon-m-globe-alt')
                    ->form([
                        Select::make('siteId')
                            ->label('Sito')
                            ->options($sites)
                            ->default($this->siteId)
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->siteId = (int) $data['siteId'])
                : null,

            Action::make('period')
                ->label('Periodo')
                ->icon('heroicon-m-calendar-days')
                ->form([
                    Select::make('days')
                        ->label('Periodo')
                        ->options([
                            7 => 'Ultimi 7 giorni',
                            28 => 'Ultimi 28 giorni',
                            90 => 'Ultimi 90 giorni',
                        ])
                        ->default($this->days)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->days = (int) $data['days']),

            Action::make('refresh')
                ->label('Aggiorna')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                // I dati stanno in cache un'ora: senza questo bottone, chi ha
                // appena corretto qualcosa su GA4 dovrebbe aspettare.
                ->action(function (): void {
                    $site = $this->site();

                    if ($site !== null) {
                        foreach (WebAnalyticsService::ALLOWED_DAYS as $days) {
                            Cache::forget("ga4:overview:{$site->id}:{$site->property_id}:{$days}");
                        }

                        Cache::forget("ga4:realtime:{$site->property_id}");
                    }

                    Notification::make()->title('Dati aggiornati da Google Analytics')->success()->send();
                }),
        ]));
    }

    public function site(): ?AnalyticsSite
    {
        return $this->siteId === null ? null : AnalyticsSite::query()->find($this->siteId);
    }

    /**
     * @return array<string, mixed>|null null quando non c'è ancora un sito configurato
     */
    public function overview(): ?array
    {
        $site = $this->site();

        return $site === null ? null : app(WebAnalyticsService::class)->overview($site, $this->days);
    }

    public function serviceAccountEmail(): ?string
    {
        return app(WebAnalyticsService::class)->serviceAccountEmail();
    }

    protected static function requiredAbility(): string
    {
        return 'canManageEditorial';
    }
}
