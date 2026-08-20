<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\NewsletterSubscriberResource;
use App\Filament\Widgets\Analytics\NewsletterKpiWidget;
use App\Filament\Widgets\Analytics\NewsletterRatesWidget;
use App\Filament\Widgets\Analytics\NewsletterTrendWidget;
use App\Filament\Widgets\Analytics\NewsletterVolumeWidget;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterAnalyticsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Andamento della newsletter: iscritti (dati nostri) e risultati delle campagne
 * (dati di ActiveCampaign, che le invia).
 *
 * La distinzione fra le due fonti non è un dettaglio implementativo: se
 * ActiveCampaign non risponde, la metà che conosciamo per certo resta.
 *
 * L'anagrafica degli iscritti è in fondo alla pagina: era una voce di menu a
 * sé ({@see NewsletterSubscriberResource}, che resta raggiungibile via URL ma
 * non compare più in navigazione), e chi guardava i numeri doveva cambiare
 * schermata per agire sul singolo contatto.
 */
class NewsletterAnalyticsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RestrictsAccessByRole;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Analytics Newsletter';

    protected static ?string $title = 'Analytics Newsletter';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'analytics/newsletter';

    protected static string $view = 'filament.pages.newsletter-analytics';

    public int $days = 28;

    protected function getHeaderWidgets(): array
    {
        return [
            NewsletterKpiWidget::class,
            NewsletterRatesWidget::class,
            NewsletterVolumeWidget::class,
            NewsletterTrendWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        // Due colonne: i grafici delle campagne si leggono affiancati, tassi a
        // sinistra e volumi a destra, perché è il confronto fra i due che
        // spiega i picchi.
        return 2;
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['days' => $this->days];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activecampaign')
                ->label('Apri ActiveCampaign')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => filled(config('services.activecampaign.url')))
                ->url(fn (): string => rtrim((string) config('services.activecampaign.url'), '/').'/app/campaigns')
                ->openUrlInNewTab(),

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
                            365 => 'Ultimo anno',
                        ])
                        ->default($this->days)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->days = (int) $data['days']),

            Action::make('refresh')
                ->label('Aggiorna')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    Cache::forget('newsletter:campaigns:12');

                    Notification::make()->title('Campagne ricaricate da ActiveCampaign')->success()->send();
                }),
        ];
    }

    /**
     * L'elenco degli iscritti, con le stesse colonne e azioni della vecchia
     * voce di menu: la definizione resta sulla resource, qui si riusa.
     */
    public function table(Table $table): Table
    {
        return NewsletterSubscriberResource::table($table)
            ->query(NewsletterSubscriber::query())
            ->heading('Iscritti')
            ->description('Archivio del sito: chi si è iscritto dal form pubblico e lo stato della sincronizzazione con ActiveCampaign.');
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return app(NewsletterAnalyticsService::class)->overview($this->days);
    }

    protected static function requiredAbility(): string
    {
        return 'canManageEditorial';
    }
}
