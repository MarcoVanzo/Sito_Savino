<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\RestrictsAccessByRole;
use App\Filament\Widgets\CustomersWidget;
use App\Filament\Widgets\OrdersByStatusWidget;
use App\Filament\Widgets\PageViewsWidget;
use App\Filament\Widgets\PaymentMethodsWidget;
use App\Filament\Widgets\SalesTrendWidget;
use App\Filament\Widgets\ShopKpiWidget;
use App\Filament\Widgets\TopProductsWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;

class ShopAnalyticsPage extends Page
{
    use RestrictsAccessByRole;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics Shop';

    protected static ?string $title = 'Analytics Shop';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'shop/analytics';

    protected string $view = 'filament.pages.shop-analytics';

    /**
     * Periodi selezionabili, in giorni. È anche la lista dei valori ammessi:
     * `$period` è una proprietà pubblica Livewire, quindi scrivibile dal client,
     * e finisce in `subDays()` e in un `CarbonPeriod` giorno per giorno. Un valore
     * arbitrario (50.000) costa una manciata di MB e un HTML da più di un mega per
     * ogni richiesta, quindi si accetta solo ciò che il menu offre davvero.
     */
    private const PERIOD_OPTIONS = [
        7 => 'Ultimi 7 giorni',
        30 => 'Ultimi 30 giorni',
        90 => 'Ultimi 90 giorni',
        365 => 'Ultimo anno',
    ];

    private const DEFAULT_PERIOD = 30;

    public int $period = self::DEFAULT_PERIOD;

    protected function getHeaderWidgets(): array
    {
        return [
            ShopKpiWidget::class,
            SalesTrendWidget::class,
            TopProductsWidget::class,
            OrdersByStatusWidget::class,
            PaymentMethodsWidget::class,
            PageViewsWidget::class,
            CustomersWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('period')
                ->label('Periodo')
                ->icon('heroicon-m-calendar-days')
                ->schema([
                    Select::make('period')
                        ->label('Periodo')
                        ->options(self::PERIOD_OPTIONS)
                        ->default($this->period)
                        ->required()
                        ->in(array_keys(self::PERIOD_OPTIONS)),
                ])
                ->action(fn (array $data) => $this->period = (int) $data['period']),
        ];
    }

    /**
     * Il nome del metodo non è libero: Filament v3 chiama getWidgetData() (vedi
     * Filament\Pages\Page e la view components/page/index.blade.php). Un metodo
     * chiamato diversamente non viene mai invocato e i widget restano al default.
     *
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['period' => $this->selectedPeriod()];
    }

    /**
     * Il periodo scelto, ridotto a uno di quelli offerti dal menu. La validazione
     * dell'azione copre il percorso normale, non un aggiornamento Livewire
     * costruito a mano: questo è l'unico punto da cui il valore raggiunge i
     * widget, quindi è qui che va chiuso.
     */
    private function selectedPeriod(): int
    {
        return array_key_exists($this->period, self::PERIOD_OPTIONS)
            ? $this->period
            : self::DEFAULT_PERIOD;
    }

    protected static function requiredAbility(): string
    {
        return 'canManageShop';
    }
}
